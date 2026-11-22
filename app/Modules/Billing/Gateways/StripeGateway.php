<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\InboundPayment;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Contracts\ReconcileResult;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Stripe adapter.
 *
 * Webhook verification is implemented per Stripe's documented scheme
 * (Stripe-Signature: t=timestamp,v1=HMAC-SHA256 of "{t}.{payload}") and is
 * fully offline-testable — the Mono Market gate 2.4 matrix (success,
 * duplicate, wrong amount, replay, tampered signature) runs in CI without
 * network. Checkout-session creation needs real keys and runs only in the
 * sandbox/live environment.
 */
final class StripeGateway implements PaymentGateway
{
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public function key(): string
    {
        return 'stripe';
    }

    public function secretKey(): ?string
    {
        $key = config('clot-billing.gateways.stripe.secret_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function webhookSecret(): ?string
    {
        $secret = config('clot-billing.gateways.stripe.webhook_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function startCheckout(Workspace $workspace, Plan $plan): ?string
    {
        $secret = $this->secretKey();

        if ($secret === null) {
            throw new RuntimeException(
                'Stripe secret key is not configured. Set it in Admin → Billing → Payment providers.'
            );
        }

        $session = (new StripeClient($secret))->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) round($plan->monthlyPriceUsd() * 100),
                    'product_data' => ['name' => 'CLOT '.$plan->name],
                    'recurring' => ['interval' => 'month'],
                ],
                'quantity' => 1,
            ]],
            'client_reference_id' => 'ws-'.$workspace->id,
            'metadata' => [
                'workspace_reference' => 'ws-'.$workspace->id,
                'plan_slug' => $plan->slug,
            ],
            'success_url' => route('billing.show').'?checkout=success',
            'cancel_url' => route('billing.show').'?checkout=cancelled',
        ]);

        return $session->url ?? null;
    }

    public function verifyWebhook(Request $request): ?InboundPayment
    {
        $secret = $this->webhookSecret();

        if ($secret === null) {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        $payload = $request->getContent();
        $header = (string) $request->header('Stripe-Signature', '');

        if (! $this->signatureIsValid($payload, $header, $secret)) {
            return null;
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! isset($event['id'], $event['type'], $event['data']['object'])) {
            return null;
        }

        $type = $this->mapType((string) $event['type']);

        if ($type === null) {
            return null; // Unrelated Stripe events are acknowledged, not billed.
        }

        $object = $event['data']['object'];
        $amountUsd = ((int) ($object['amount_total'] ?? 0)) / 100;

        return new InboundPayment(
            providerEventId: (string) $event['id'],
            type: $type,
            amountUsd: $amountUsd,
            currency: strtoupper((string) ($object['currency'] ?? 'usd')),
            providerReference: (string) ($object['metadata']['workspace_reference'] ?? $object['client_reference_id'] ?? ''),
            payload: $event,
        );
    }

    /** Stripe event type → CLOT payment type (null = ignore). */
    private function mapType(string $stripeType): ?string
    {
        return match ($stripeType) {
            'checkout.session.completed', 'invoice.paid' => 'subscription.payment',
            'invoice.payment_failed' => 'payment.failed',
            default => null,
        };
    }

    public function reconcile(Subscription $subscription): ?ReconcileResult
    {
        $secret = $this->secretKey();

        if ($secret === null || $subscription->provider_reference === null) {
            return null;
        }

        try {
            $session = (new StripeClient($secret))->checkout->sessions->retrieve(
                $subscription->provider_reference,
            );

            return new ReconcileResult(
                status: $session->payment_status === 'paid' ? SubscriptionStatus::Active : SubscriptionStatus::PastDue,
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe reconcile failed', ['reference' => $subscription->provider_reference, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Stripe's documented scheme: "t=timestamp,v1=signature" where signature
     * = HMAC-SHA256("{timestamp}.{payload}", webhook_secret). Timestamp must
     * be within tolerance (replay window).
     */
    public function signatureIsValid(string $payload, string $header, string $secret): bool
    {
        $parts = [];

        foreach (explode(',', $header) as $piece) {
            $pair = explode('=', trim($piece), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }

    /** @return array<string, string>|null */
}
