<?php

use App\Modules\Billing\Services\PlanFeatures;
use App\Modules\Collection\Services\Budget\AllowanceService;

it('claims answers from the allowance and stops at zero', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    activatePlanWithAllowance($workspace, 3);

    actAsWorkspaceMember($owner, $workspace);
    $allowance = app(AllowanceService::class);

    expect($allowance->claim('cell-a')['claimed'])->toBeTrue()
        ->and($allowance->claim('cell-b')['claimed'])->toBeTrue()
        ->and($allowance->claim('cell-c')['claimed'])->toBeTrue()
        ->and($allowance->used())->toBe(3)
        ->and($allowance->remaining())->toBe(0)
        ->and($allowance->claim('cell-d')['claimed'])->toBeFalse();
});

it('never double-claims the same cell (safe retries)', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    activatePlanWithAllowance($workspace, 1);

    actAsWorkspaceMember($owner, $workspace);
    $allowance = app(AllowanceService::class);

    $first = $allowance->claim('cell-same');
    $second = $allowance->claim('cell-same');

    expect($first)->toBe(['claimed' => true, 'existing' => false])
        ->and($second)->toBe(['claimed' => true, 'existing' => true])
        ->and($allowance->used())->toBe(1);
});

it('refunds claims for failed collections', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    activatePlanWithAllowance($workspace, 2);

    actAsWorkspaceMember($owner, $workspace);
    $allowance = app(AllowanceService::class);

    $allowance->claim('cell-a');
    $allowance->claim('cell-b');
    expect($allowance->used())->toBe(2);

    $allowance->refund('cell-a');
    expect($allowance->used())->toBe(1)
        ->and($allowance->remaining())->toBe(1)
        ->and($allowance->claim('cell-c')['claimed'])->toBeTrue();
});

it('falls back to the default plan allowance when no plan is attached', function (): void {
    [, $workspace] = makeWorkspaceWithOwner();
    actAsWorkspaceMember($workspace->owner, $workspace);

    expect(app(AllowanceService::class)->allowance())
        ->toBe(app(PlanFeatures::class)->allowance($workspace));
});
