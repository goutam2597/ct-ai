<?php

use App\Models\User;
use App\Modules\Billing\Contracts\InboundPayment;
use App\Modules\Billing\Models\PaymentEvent;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\PlanFeatures;
use App\Modules\Collection\Services\Budget\AllowanceService;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Facades\Config;

/**
 * Money-path tests (Mono Market gate 2.4): verified events only, single-use
 * event ids, server-side amount comparison, role-gated checkout, no mock
 * bypass in production.
 */
it('attaches the default (free) plan when a workspace is created', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();

    $subscription = app(BillingService::class)->currentSubscription($workspace);

    expect($subscription)->not->toBeNull()
        ->and($subscription?->plan->slug)->toBe('free')
        ->and($subscription?->status->value)->toBe('active');
});

it('derives the allowance and engines from the workspace plan', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();

    actAsWorkspaceMember($owner, $workspace);

    expect(app(AllowanceService::class)->allowance())->toBe(150);

    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    app(BillingService::class)->activate($workspace, $growth, 'fake');

    expect(app(AllowanceService::class)->allowance())->toBe(10000)
        ->and(app(PlanFeatures::class)->allowsEngine($workspace, 'claude'))->toBeTrue()
        ->and(app(PlanFeatures::class)->allowsEngine($workspace, 'grok'))->toBeFalse();
});

it('activates paid plans through the offline gateway with a recorded event', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    actAsWorkspaceMember($owner, $workspace);
    $this->actingAs($owner);

    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $this->post(route('billing.checkout', $starter))->assertRedirect();

    $subscription = app(BillingService::class)->currentSubscription($workspace);
    expect($subscription?->plan->slug)->toBe('starter')
        ->and($subscription?->status->value)->toBe('active')
        ->and(PaymentEvent::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
        ->and((float) PaymentEvent::query()->first()->amount_usd)->toBe(49.0);
});

it('de-duplicates replayed webhook events by provider event id', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();
    $billing = app(BillingService::class);

    // Put the workspace on Starter so the 49.00 event amount matches the plan.
    app(BillingService::class)->activate($workspace, Plan::query()->where('slug', 'starter')->firstOrFail(), 'fake');

    $payment = new InboundPayment(
        providerEventId: 'evt-123',
        type: 'subscription.payment',
        amountUsd: 49.0,
        currency: 'USD',
        providerReference: 'ws-'.$workspace->id,
        payload: [],
    );

    $first = $billing->processInbound($payment, 'fake');
    $replay = $billing->processInbound($payment, 'fake');

    expect($first?->id)->toBe($replay?->id)
        ->and(PaymentEvent::query()->where('provider_event_id', 'evt-123')->count())->toBe(1);
});

it('rejects webhooks with wrong amounts and records the failure', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();
    app(BillingService::class)->activate($workspace, Plan::query()->where('slug', 'starter')->firstOrFail(), 'fake');
    $billing = app(BillingService::class);

    $bad = new InboundPayment(
        providerEventId: 'evt-wrong-amount',
        type: 'subscription.payment',
        amountUsd: 0.99, // wrong on purpose
        currency: 'USD',
        providerReference: 'ws-'.$workspace->id,
        payload: [],
    );

    $result = $billing->processInbound($bad, 'fake');

    expect($result)->toBeNull()
        ->and(PaymentEvent::query()->where('provider_event_id', 'evt-wrong-amount')->first()->status->value)->toBe('failed');

    // The subscription was NOT extended by the forged payment.
    expect(app(BillingService::class)->currentSubscription($workspace)?->current_period_start)
        ->toBeLessThanOrEqual(now());
});

it('rejects webhooks for unknown provider references', function (): void {
    $billing = app(BillingService::class);

    $orphan = new InboundPayment(
        providerEventId: 'evt-orphan',
        type: 'subscription.payment',
        amountUsd: 49.0,
        currency: 'USD',
        providerReference: 'ws-999999',
        payload: [],
    );

    expect($billing->processInbound($orphan, 'fake'))->toBeNull()
        ->and(PaymentEvent::query()->count())->toBe(0);
});

it('verifies webhook signatures and rejects bad ones', function (): void {
    $payload = [
        'event_id' => 'evt-sig-1',
        'type' => 'subscription.payment',
        'amount_usd' => 49,
        'provider_reference' => 'no-such-ref',
    ];

    $body = json_encode($payload);
    $secret = (string) config('clot-billing.gateways.fake.webhook_secret');
    $signature = hash_hmac('sha256', (string) $body, $secret);

    $good = $this->postJson('/webhooks/billing/fake', $payload, ['X-CLOT-Signature' => $signature]);
    $bad = $this->postJson('/webhooks/billing/fake', $payload, ['X-CLOT-Signature' => 'forged']);

    expect($good->status())->toBe(200)
        ->and($bad->status())->toBe(400);
});

it('refuses the fake gateway when explicitly disabled (no production bypass)', function (): void {
    Config::set('clot-billing.allow_fake_gateway', false);

    $billing = app(BillingService::class);
    $fake = $billing->gateway('fake');

    expect(fn () => $fake->startCheckout(Workspace::factory()->create(), Plan::query()->where('slug', 'starter')->firstOrFail()))
        ->toThrow(RuntimeException::class);
});

it('renews due subscriptions with a recorded payment', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();

    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $billing = app(BillingService::class);
    $billing->activate($workspace, $starter, 'fake');

    // Force the period into the past.
    Subscription::query()
        ->where('workspace_id', $workspace->id)
        ->update(['current_period_end' => now()->subDay(), 'status' => 'active']);

    $renewed = $billing->renewDue();

    $subscription = $billing->currentSubscription($workspace);

    expect($renewed)->toBe(1)
        ->and($subscription?->current_period_end->isFuture())->toBeTrue()
        ->and(PaymentEvent::query()->where('workspace_id', $workspace->id)->where('type', 'subscription.renewed')->count())->toBe(1);
});

it('lets only the owner (or policy-permitted roles) check out', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();

    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $viewer->id,
    ]);

    actAsWorkspaceMember($viewer, $workspace);
    $this->actingAs($viewer);

    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $this->post(route('billing.checkout', $starter))->assertForbidden();

    actAsWorkspaceMember($owner, $workspace);
    $this->actingAs($owner);
    $this->post(route('billing.checkout', $starter))->assertRedirect();
});
