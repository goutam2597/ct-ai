<?php

namespace App\Modules\Billing\Gateways;

use App\Modules\Billing\Contracts\InboundPayment;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Contracts\ReconcileResult;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Offline gateway used for local installs, demos and tests. NOT a real money
 * path: it refuses to run in production (Mono Market gate 2.4 "no mock
 * bypass"), and its webhooks still pass through the SAME signature + single-use
 * + amount verification pipeline as real gateways.
 */
final class FakeGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'fake';
    }

    public function startCheckout(Workspace $workspace, Plan $plan): ?string
    {
        $this->guardProduction();

        // No redirect: BillingService activates the subscription directly and
        // records a payment event with the plan's price as the expected amount.
        return null;
    }

    /**
     * Verify a fake webhook. Signature: HMAC-SHA256 of the raw body with the
     * fake provider secret, in X-CLOT-Signature — same mechanism real
     * gateways must implement.
     */
    public function verifyWebhook(Request $request): ?InboundPayment
    {
        $this->guardProduction();

        $secret = (string) config('clot-billing.gateways.fake.webhook_secret', 'fake-secret');

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);
        $given = (string) $request->header('X-CLOT-Signature', '');

        if ($given === '' || ! hash_equals($expected, $given)) {
            return null;
        }

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            return null;
        }

        return new InboundPayment(
            providerEventId: (string) ($payload['event_id'] ?? ''),
            type: (string) ($payload['type'] ?? 'subscription.payment'),
            amountUsd: (float) ($payload['amount_usd'] ?? -1),
            currency: (string) ($payload['currency'] ?? 'USD'),
            providerReference: isset($payload['provider_reference']) ? (string) $payload['provider_reference'] : null,
            payload: $payload,
        );
    }

    public function reconcile(Subscription $subscription): ReconcileResult
    {
        $this->guardProduction();

        // The offline gateway always agrees with the local record.
        return new ReconcileResult(
            status: $subscription->status,
            periodEnd: $subscription->current_period_end,
        );
    }

    private function guardProduction(): void
    {
        if (! config('clot-billing.allow_fake_gateway', false)) {
            throw new RuntimeException(
                'The fake payment gateway is disabled. Enable it explicitly with CLOT_ALLOW_FAKE_GATEWAY=true '
                .'for local installs and demos only. Production must use a real gateway.'
            );
        }
    }
}
