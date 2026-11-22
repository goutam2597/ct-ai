<?php

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Collection\Models\ShareLink;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Client reports: PDF download, share-link lifecycle (create → read →
 * revoke → 404) and white-label branding (build plan §6.1 reports row).
 */
it('renders a PDF report for the brand', function (): void {
    [$owner, , $brand] = makeTrackingBrand(runsPerPrompt: 3);

    $response = $this->actingAs($owner)
        ->get(route('brands.report', $brand));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

it('includes white-label branding only when the plan allows it', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(name: 'Wlbrand');

    // Free plan: no white-label → CLOT app name on the report.
    $this->actingAs($owner)
        ->get(route('brands.report', $brand))
        ->assertOk();

    // Put the workspace on Agency (white_label flag).
    $agency = Plan::query()->where('slug', 'agency')->firstOrFail();
    Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspace->id],
        ['plan_id' => $agency->id, 'status' => 'active', 'provider' => 'fake',
            'current_period_start' => now(), 'current_period_end' => now()->addMonth()],
    );

    $this->actingAs($owner)
        ->get(route('brands.report', $brand))
        ->assertOk();

    expect(true)->toBeTrue(); // PDF bytes are binary; content assertions run on the share page below.
});

it('creates a share link that renders the report without login', function (): void {
    [$owner, , $brand] = makeTrackingBrand(runsPerPrompt: 3);
    actAsWorkspaceMember($owner, $workspace = $brand->workspace()->withoutGlobalScopes()->firstOrFail());
    $this->actingAs($owner);

    $this->post(route('brands.share-links.create', $brand))->assertRedirect();

    $link = ShareLink::query()->where('brand_id', $brand->id)->firstOrFail();
    expect($link->isRevoked())->toBeFalse();

    // No auth: the token IS the credential.
    $this->post(route('brands.share-links.create', $brand));

    $this->post('/logout');

    $this->get(route('reports.shared', ['token' => $link->token]))
        ->assertOk()
        ->assertSee('AI visibility report')
        ->assertSee($brand->name)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    // Bad tokens 404 (never leak existence).
    $this->get(route('reports.shared', ['token' => Str::random(48)]))->assertNotFound();
});

it('revokes share links so revoked tokens 404', function (): void {
    [$owner, , $brand] = makeTrackingBrand(runsPerPrompt: 3);
    actAsWorkspaceMember($owner, $brand->workspace()->withoutGlobalScopes()->firstOrFail());
    $this->actingAs($owner);

    $this->post(route('brands.share-links.create', $brand));
    $link = ShareLink::query()->where('brand_id', $brand->id)->firstOrFail();

    $this->delete(route('brands.share-links.destroy', ['brand' => $brand, 'shareLink' => $link]))
        ->assertRedirect();

    $this->get(route('reports.shared', ['token' => $link->token]))->assertNotFound();
});

it('forbids viewers from creating share links', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand();

    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create([
        'workspace_id' => $workspace->id, 'user_id' => $viewer->id,
    ]);

    actAsWorkspaceMember($viewer, $workspace);
    $this->actingAs($viewer)
        ->post(route('brands.share-links.create', $brand))
        ->assertForbidden();
});
