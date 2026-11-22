<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\InboundPayment;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Enums\PaymentEventStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\PaymentEvent;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * All money-state changes flow through here (ADR-0001 rule 2). Enforces the
 * Mono Market gate 2.4 properties: verified events only, single-use event
 * ids, server-side amount comparison, row locking on the subscription.
 */
final class BillingService
{
    public function __construct(
        /** @var array<string, PaymentGateway> */
        private readonly array $gateways = [],
    ) {}

    public function gateway(?string $key = null): PaymentGateway
    {
        $key ??= (string) config('clot-billing.default_gateway', 'fake');

        if (! isset($this->gateways[$key])) {
            throw new RuntimeException("Unknown payment gateway [{$key}].");
        }

        return $this->gateways[$key];
    }

    /** Attach the default (free) plan at workspace creation. */
    public function attachDefaultPlan(Workspace $workspace): ?Subscription
    {
        $plan = Plan::defaultPlan();

        if ($plan === null) {
            return null;
        }

        $existing = Subscription::query()->where('workspace_id', $workspace->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->activate($workspace, $plan, null);
    }

    /** Start checkout for a plan. Redirect-based gateways return a URL. */
    public function checkout(Workspace $workspace, Plan $plan, ?string $gatewayKey = null): ?string
    {
        $gateway = $this->gateway($gatewayKey);
        $url = $gateway->startCheckout($workspace, $plan);

        $price = $plan->monthlyPriceUsd();

        if ($price <= 0) {
            $this->activate($workspace, $plan, $gateway->key());
        } elseif (config('clot-billing.trial_days', 7) > 0 && $this->currentSubscription($workspace) === null) {
            $this->activate($workspace, $plan, $gateway->key(), trialDays: (int) config('clot-billing.trial_days'));
        } else {
            // Offline gateway: activate first, then record the simulated money
            // event so the payment ledger shows the charge. Real gateways
            // activate from their verified webhook instead.
            $subscription = $this->activate($workspace, $plan, $gateway->key());

            $this->processInbound(
                new InboundPayment(
                    providerEventId: 'fake-'.bin2hex(random_bytes(12)),
                    type: 'subscription.activated',
                    amountUsd: $price,
                    currency: 'USD',
                    providerReference: $subscription->provider_reference,
                    payload: ['simulated' => true, 'plan' => $plan->slug],
                ),
                $gateway->key(),
            );
        }

        return $url;
    }

    /** The subscription row is the money-state anchor; created inside a transaction. */
    public function activate(Workspace $workspace, Plan $plan, ?string $provider, int $trialDays = 0): Subscription
    {
        return DB::transaction(function () use ($workspace, $plan, $provider, $trialDays): Subscription {
            $now = now();

            $subscription = Subscription::query()->updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'plan_id' => $plan->id,
                    'status' => $trialDays > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                    'provider' => $provider ?? 'fake',
                    'provider_reference' => 'ws-'.$workspace->id,
                    'current_period_start' => $now,
                    'current_period_end' => $now->copy()->addMonth(),
                    'trial_ends_at' => $trialDays > 0 ? $now->copy()->addDays($trialDays) : null,
                ],
            );

            return $subscription;
        });
    }

    public function currentSubscription(Workspace $workspace): ?Subscription
    {
        /** @var Subscription|null */
        return Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('status', [SubscriptionStatus::Canceled->value])
            ->first();
    }

    /**
     * Process a verified gateway event: de-duplicated, amount-checked,
     * locked. Returns the payment event row, or null when the webhook was
     * rejected (bad reference, wrong amount) — the caller responds 4xx.
     */
    public function processInbound(InboundPayment $payment, string $provider): ?PaymentEvent
    {
        if ($payment->providerEventId === '') {
            return null;
        }

        // De-duplication FIRST: a replayed event id is a no-op (gate 2.4).
        $existing = PaymentEvent::query()
            ->where('provider', $provider)
            ->where('provider_event_id', $payment->providerEventId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $subscription = Subscription::query()
            ->where('provider', $provider)
            ->where('provider_reference', $payment->providerReference ?? '')
            ->first();

        if ($subscription === null) {
            return null;
        }

        // Server-side amount comparison against the plan price (gate 2.4).
        $expected = $subscription->plan->monthlyPriceUsd();

        if ($payment->type === 'subscription.payment' && abs($payment->amountUsd - $expected) > 0.001) {
            PaymentEvent::query()->create([
                'workspace_id' => $subscription->workspace_id,
                'subscription_id' => $subscription->id,
                'provider' => $provider,
                'provider_event_id' => $payment->providerEventId,
                'type' => $payment->type,
                'amount_usd' => $payment->amountUsd,
                'currency' => $payment->currency,
                'status' => PaymentEventStatus::Failed,
                'payload' => $payment->payload,
                'processed_at' => null,
            ]);

            return null;
        }

        return DB::transaction(function () use ($payment, $subscription, $provider): PaymentEvent {
            $event = PaymentEvent::query()->create([
                'workspace_id' => $subscription->workspace_id,
                'subscription_id' => $subscription->id,
                'provider' => $provider,
                'provider_event_id' => $payment->providerEventId,
                'type' => $payment->type,
                'amount_usd' => $payment->amountUsd,
                'currency' => $payment->currency,
                'status' => PaymentEventStatus::Received,
                'payload' => $payment->payload,
            ]);

            // Serialize concurrent money-state changes on this row (gate 2.4):
            // SELECT ... FOR UPDATE on the subscription before writing.
            Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->first();

            $locked = Subscription::query()->findOrFail($subscription->getKey());

            match ($payment->type) {
                'subscription.payment' => $this->extendPeriod($locked),
                'subscription.renewed' => $this->extendPeriod($locked),
                'subscription.activated' => null, // Period set by activate(); event recorded for the ledger.
                'payment.failed' => $locked->forceFill(['status' => SubscriptionStatus::PastDue])->save(),
                default => null,
            };

            $event->forceFill([
                'status' => PaymentEventStatus::Processed,
                'processed_at' => now(),
            ])->save();

            return $event;
        });
    }

    /** Renew every active/trialing subscription whose period has ended. */
    public function renewDue(): int
    {
        $dueIds = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Trialing->value])
            ->where('current_period_end', '<=', now())
            ->pluck('id');

        $renewed = 0;

        foreach ($dueIds as $subscriptionId) {
            $subscription = Subscription::query()->findOrFail($subscriptionId);
            $gateway = $this->gateway($subscription->provider);

            try {
                $reconcile = $gateway->reconcile($subscription);

                if ($reconcile !== null && $reconcile->status !== null) {
                    $subscription->forceFill(['status' => $reconcile->status])->save();
                }
            } catch (RuntimeException) {
                continue; // Disabled gateway: skip without breaking the sweep.
            }

            $price = $subscription->plan->monthlyPriceUsd();

            if ($price > 0 && $subscription->status === SubscriptionStatus::Active) {
                $this->recordAndApplyPayment(
                    new InboundPayment(
                        providerEventId: 'renew-'.$subscription->id.'-'.$subscription->current_period_end?->format('Ymd'),
                        type: 'subscription.renewed',
                        amountUsd: $price,
                        currency: 'USD',
                        providerReference: $subscription->provider_reference,
                        payload: ['simulated' => true],
                    ),
                    $subscription->provider,
                );
            } else {
                $this->extendPeriod($subscription);
            }

            $renewed++;
        }

        return $renewed;
    }

    public function cancel(Workspace $workspace): void
    {
        Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->update(['status' => SubscriptionStatus::Canceled->value]);
    }

    private function recordAndApplyPayment(InboundPayment $payment, string $provider): void
    {
        $this->processInbound($payment, $provider);
    }

    private function extendPeriod(Subscription $subscription): void
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ])->save();
    }
}
