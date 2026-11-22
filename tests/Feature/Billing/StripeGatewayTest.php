<?php

use App\Modules\Billing\Gateways\StripeGateway;
use App\Modules\Billing\Models\PaymentEvent;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;

/**
 * Stripe adapter tests — the Mono Market gate 2.4 matrix, all OFFLINE
 * (webhook signature verification is Stripe's documented HMAC scheme, so
 * success / duplicate / wrong-amount / replay / tampered run in CI).
 */
function stripeEventPayload(string $eventId, int $amountCents, string $workspaceReference = 'ws-1'): string
{
    return json_encode([
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'amount_total' => $amountCents,
            'currency' => 'usd',
            'client_reference_id' => null,
            'metadata' => ['workspace_reference' => $workspaceReference],
        ]],
    ]);
}

function stripeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
{
    $t = $timestamp ?? time();

    return 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, $secret);
}

beforeEach(function (): void {
    Config::set([
        'clot-billing.gateways.stripe.webhook_secret' => 'whsec_test_123',
        'clot-billing.gateways.stripe.secret_key' => 'sk_test_123',
    ]);
});

/** POST the EXACT bytes with the signature header (Stripe webhooks are raw-body signed). */
function postStripeWebhook(string $payload, string $header): TestResponse
{
    $response = app(Kernel::class)->handle(
        Request::create(
            '/webhooks/billing/stripe',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => $header,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        ),
    );

    return TestResponse::fromBaseResponse($response);
}

it('processes a valid stripe webhook and activates the subscription', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();

    $billing = app(BillingService::class);
    $billing->activate($workspace, Plan::query()->where('slug', 'starter')->firstOrFail(), 'stripe');

    $payload = stripeEventPayload('evt_ok_1', 4900, 'ws-'.$workspace->id);
    $header = stripeSignatureHeader($payload, 'whsec_test_123');

    postStripeWebhook($payload, $header)->assertOk();

    expect($billing->currentSubscription($workspace)?->plan->slug)->toBe('starter')
        ->and(PaymentEvent::query()->where('provider', 'stripe')->where('status', 'processed')->count())->toBe(1);
});

it('rejects tampered stripe signatures', function (): void {
    $payload = stripeEventPayload('evt_tampered', 4900);

    postStripeWebhook($payload, 't='.time().',v1='.str_repeat('0', 64))->assertStatus(400);

    expect(PaymentEvent::query()->count())->toBe(0);
});

it('rejects stripe webhooks outside the replay window', function (): void {
    $payload = stripeEventPayload('evt_old', 4900);
    $stale = time() - 4000; // beyond the 300s tolerance

    postStripeWebhook($payload, stripeSignatureHeader($payload, 'whsec_test_123', $stale))->assertStatus(400);
});

it('de-duplicates replayed stripe events (identical event id)', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();
    app(BillingService::class)->activate($workspace, Plan::query()->where('slug', 'starter')->firstOrFail(), 'stripe');

    $payload = stripeEventPayload('evt_replay', 4900, 'ws-'.$workspace->id);
    $header = stripeSignatureHeader($payload, 'whsec_test_123');

    postStripeWebhook($payload, $header)->assertOk();

    postStripeWebhook($payload, $header)->assertOk();

    expect(PaymentEvent::query()->where('provider', 'stripe')->count())->toBe(1);
});

it('records wrong-amount stripe payments as failed and never applies them', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();
    $billing = app(BillingService::class);
    $billing->activate($workspace, Plan::query()->where('slug', 'starter')->firstOrFail(), 'stripe');
    $periodEndBefore = $billing->currentSubscription($workspace)?->current_period_end;

    $payload = stripeEventPayload('evt_wrong', 100, 'ws-'.$workspace->id); // $1.00 — not $49
    postStripeWebhook($payload, stripeSignatureHeader($payload, 'whsec_test_123'))->assertOk();

    $event = PaymentEvent::query()->where('provider', 'stripe')->firstOrFail();

    expect($event->status->value)->toBe('failed')
        ->and($billing->currentSubscription($workspace)?->current_period_end)->toEqual($periodEndBefore);
});

it('maps invoice.payment_failed to past_due', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();
    app(BillingService::class)->activate($workspace, Plan::query()->where('slug', 'starter')->firstOrFail(), 'stripe');

    $payload = json_encode([
        'id' => 'evt_fail',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => [
            'amount_total' => 0,
            'currency' => 'usd',
            'metadata' => ['workspace_reference' => 'ws-'.$workspace->id],
        ]],
    ]);

    postStripeWebhook($payload, stripeSignatureHeader($payload, 'whsec_test_123'))->assertOk();

    expect(app(BillingService::class)->currentSubscription($workspace)?->status->value)->toBe('past_due');
});

it('refuses checkout without configured keys instead of half-configuring', function (): void {
    Config::set('clot-billing.gateways.stripe.secret_key', null);

    $gateway = new StripeGateway;
    $workspace = Workspace::factory()->create();

    expect(fn () => $gateway->startCheckout($workspace, Plan::query()->where('slug', 'starter')->firstOrFail()))
        ->toThrow(RuntimeException::class, 'not configured');
});
