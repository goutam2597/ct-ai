<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Payment provider keys screen: the operator writes keys to .env; customers
 * never see it (gate 2.8: secrets only in .env, never rendered back).
 */
beforeEach(function (): void {
    $tmp = sys_get_temp_dir().'/clot-billingkeys-'.bin2hex(random_bytes(4));
    mkdir($tmp, 0777, true);

    File::put($tmp.'/.env', "APP_ENV=local\n");
    config(['clot-installer.env_path' => $tmp.'/.env']);
    $this->tmp = $tmp;
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

it('writes stripe keys to .env when the operator saves', function (): void {
    $operator = User::factory()->create(['is_operator' => true]);

    $this->actingAs($operator)
        ->post('/admin/billing/keys', [
            'gateway' => 'stripe',
            'secret_key' => 'sk_test_operator_key',
            'webhook_secret' => 'whsec_operator',
        ])
        ->assertRedirect();

    $env = File::get($this->tmp.'/.env');

    expect($env)->toContain('STRIPE_SECRET_KEY=sk_test_operator_key')
        ->and($env)->toContain('STRIPE_WEBHOOK_SECRET=whsec_operator')
        // Existing lines survive.
        ->and($env)->toContain('APP_ENV=local');
});

it('leaves keys untouched when the field is empty', function (): void {
    $operator = User::factory()->create(['is_operator' => true]);

    File::put($this->tmp.'/.env', "STRIPE_SECRET_KEY=sk_existing\n");

    $this->actingAs($operator)
        ->post('/admin/billing/keys', [
            'gateway' => 'stripe',
            'secret_key' => '', // blank = keep
            'webhook_secret' => 'whsec_new',
        ])
        ->assertRedirect();

    $env = File::get($this->tmp.'/.env');

    expect($env)->toContain('STRIPE_SECRET_KEY=sk_existing')
        ->and($env)->toContain('STRIPE_WEBHOOK_SECRET=whsec_new');
});

it('forbids customers from the billing keys screen', function (): void {
    [$owner] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get('/admin/billing')
        ->assertForbidden();

    $this->actingAs($owner)
        ->post('/admin/billing/keys', ['gateway' => 'stripe', 'secret_key' => 'sk_evil'])
        ->assertForbidden();
});

it('shows gateway statuses to the operator', function (): void {
    Config::set('clot-billing.gateways.stripe.secret_key', 'sk_test_123');

    $operator = User::factory()->create(['is_operator' => true]);

    $this->actingAs($operator)
        ->get('/admin/billing')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/BillingKeys')
            ->has('gateways', 2)
            ->where('gateways.1.secretConfigured', true)
            ->where('gateways.0.secretConfigured', false));
});
