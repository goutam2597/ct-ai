<?php

use App\Models\User;
use App\Modules\Collection\Services\Sweep\SweepRunner;

it('renders the opportunities page with scored items', function (): void {
    [$owner, $workspace, $brand, $competitors] = makeTrackingBrand(runsPerPrompt: 3);
    app(SweepRunner::class)->runForBrand($brand);

    actAsWorkspaceMember($owner, $workspace);
    $this->actingAs($owner)
        ->get(route('brands.opportunities', $brand))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Brands/Opportunities')
            ->has('brand')
            ->has('opportunities'));
});

it('requires membership for the opportunities page', function (): void {
    [, , $brand] = makeTrackingBrand();

    $this->actingAs(User::factory()->create())
        ->get(route('brands.opportunities', $brand))
        ->assertForbidden();
});
