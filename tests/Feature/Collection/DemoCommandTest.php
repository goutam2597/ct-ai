<?php

use App\Models\User;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Tenancy\Models\Workspace;

/**
 * Demo command tests (build plan §8): seed is deterministic and free
 * (FakeCollector); reset removes ONLY demo-prefixed accounts — the installer's
 * first operator under @clot.test must never be touched.
 */
it('seeds a demo workspace with a tracked brand and collected answers', function (): void {
    $this->artisan('clot:demo')->assertSuccessful();

    $owner = User::query()->where('email', 'demo-owner@clot.test')->firstOrFail();
    $workspace = Workspace::query()->where('name', 'Acme')->firstOrFail();
    $brand = Brand::query()->where('name', 'Northwind')->firstOrFail();

    expect($workspace->owner_id)->toBe($owner->id)
        ->and(Competitor::query()->where('brand_id', $brand->id)->count())->toBe(3)
        ->and(Prompt::query()->where('brand_id', $brand->id)->count())->toBe(5)
        ->and(Answer::query()->where('brand_id', $brand->id)->where('status', 'collected')->count())->toBe(45);
    // 5 prompts × 3 engines × 3 runs × 1 market = 45 answers, free via FakeCollector

    // Second run refuses without reset.
    $this->artisan('clot:demo')->assertExitCode(1);
});

it('resets demo accounts but never the first operator account', function (): void {
    $installerUser = User::factory()->create(['email' => 'operator@clot.test', 'is_operator' => true]);

    $this->artisan('clot:demo')->assertSuccessful();

    $this->artisan('clot:demo', ['--reset' => true])->assertSuccessful();

    expect(User::query()->where('email', 'demo-owner@clot.test')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'operator@clot.test')->exists())->toBeTrue()
        ->and(User::query()->find($installerUser->id))->not->toBeNull();
});
