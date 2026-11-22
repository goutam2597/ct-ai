<?php

use App\Modules\Analytics\Services\WilsonInterval;
use App\Modules\Collection\Collectors\FakeCollector;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\AllowanceLedger;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerDailyStat;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\CostLedger;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use App\Modules\Collection\Services\CollectorManager;
use App\Modules\Collection\Services\Sweep\SweepRunner;
use Illuminate\Support\Facades\Config;

/**
 * End-to-end pipeline tests on the FakeCollector (sync queue): the suite that
 * proves ADR-0002/0004 properties — dedup, refunds, exclusions, caps.
 */
it('runs a full sweep end to end and completes', function (): void {
    [, , $brand] = makeTrackingBrand(runsPerPrompt: 3);
    // 2 prompts × 2 engines × 3 runs × 1 market = 12 cells
    $sweep = app(SweepRunner::class)->runForBrand($brand);

    expect($sweep->status->value)->toBe('completed')
        ->and($sweep->cells_total)->toBe(12)
        ->and($sweep->cells_collected)->toBe(12)
        ->and($sweep->cells_failed)->toBe(0)
        ->and(Answer::query()->where('brand_id', $brand->id)->where('status', AnswerStatus::Collected)->count())->toBe(12)
        ->and(CostLedger::query()->count())->toBe(12)
        ->and(AnswerDailyStat::query()->count())->toBeGreaterThan(0)
        ->and($brand->refresh()->next_sweep_at)->not->toBeNull();

    // Every collected answer was read for the brand AND its competitors.
    $firstAnswer = Answer::query()->where('status', AnswerStatus::Collected)->first();
    expect($firstAnswer->mentions()->count())->toBeGreaterThanOrEqual(1);
});

it('never collects the same cell twice on the same day', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    actAsWorkspaceMember($owner, $workspace);

    $runner = app(SweepRunner::class);
    $first = $runner->runForBrand($brand);
    expect($first->cells_total)->toBe(12);

    $second = $runner->runForBrand($brand);
    expect($second->cells_total)->toBe(0)
        ->and($second->cells_collected)->toBe(0)
        ->and(Answer::query()->count())->toBe(12)
        ->and(CostLedger::query()->count())->toBe(12);
});

it('stores failed answers with their reason, refunds the claim, and excludes them from rates', function (): void {
    // Bind the collector manager with injected failures so the queued jobs
    // (which resolve the manager from the container) see it too.
    $manager = new CollectorManager([
        'chatgpt' => [
            'class' => FakeCollector::class,
            'config' => ['failOn' => ['email automation']],
        ],
    ]);
    app()->instance(CollectorManager::class, $manager);

    [, , $brand] = makeTrackingBrand(
        promptTexts: [
            'What is the best CRM for a small agency?',
            'Best CRM with email automation?',
        ],
        engines: ['chatgpt'],
        runsPerPrompt: 3,
    );

    $sweep = app(SweepRunner::class)->runForBrand($brand);

    expect($sweep->cells_total)->toBe(6)
        ->and($sweep->cells_collected)->toBe(3)
        ->and($sweep->cells_failed)->toBe(3)
        ->and(Answer::query()->where('status', AnswerStatus::Failed)->count())->toBe(3);

    $failed = Answer::query()->where('status', AnswerStatus::Failed)->first();
    expect($failed->failure_reason)->toContain('simulated failure')
        ->and($failed->named_you)->toBeNull()
        ->and($failed->provider_cost_usd)->toBeNull();

    // Claims: 6 made, 3 refunded → net 3 used. Cost ledger: only successes.
    $claims = AllowanceLedger::query()->where('reason', 'answer')->count();
    $refunds = AllowanceLedger::query()->where('reason', 'refund')->count();
    expect($claims)->toBe(6)
        ->and($refunds)->toBe(3)
        ->and(CostLedger::query()->count())->toBe(3);

    // Mention rate computed over collected answers only (3 collected).
    $named = Answer::query()->where('status', AnswerStatus::Collected)->where('named_you', true)->count();
    $rate = WilsonInterval::compute($named, 3);
    expect($rate['point'])->not->toBeNull();
});

it('stops collecting when the workspace allowance runs out', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    activatePlanWithAllowance($workspace, 5);

    $sweep = app(SweepRunner::class)->runForBrand($brand);

    expect($sweep->cells_total)->toBe(12)
        ->and($sweep->cells_collected)->toBe(5)
        ->and($sweep->cells_failed)->toBe(7)
        ->and(Answer::query()->where('status', AnswerStatus::Collected)->count())->toBe(5)
        // Systemic failures store no answer rows — the cell retries next sweep.
        ->and(Answer::query()->where('status', AnswerStatus::Failed)->count())->toBe(0)
        ->and(CostLedger::query()->count())->toBe(5)
        ->and(SweepCell::query()->where('failure_reason', 'monthly allowance exhausted')->count())->toBe(7);
});

it('stops everything at the installation-wide daily spend cap', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(engines: ['chatgpt'], runsPerPrompt: 3);

    Config::set('clot-collection.daily_cap_usd', 0.02);

    $sweep = app(SweepRunner::class)->runForBrand($brand);

    // Each answer costs 0.01: two fit under the 0.02 cap, the rest are blocked.
    expect($sweep->cells_collected)->toBe(2)
        ->and($sweep->cells_failed)->toBe(4)
        ->and((float) CostLedger::query()->sum('provider_cost_usd'))->toBeLessThanOrEqual(0.02)
        ->and(SweepCell::query()->where('failure_reason', 'daily spend cap reached')->count())->toBe(4);
});

it('keeps brands invisible across workspaces', function (): void {
    [$ownerA, $workspaceA, $brandA] = makeTrackingBrand(name: 'Alpha');
    [, $workspaceB, $brandB] = makeTrackingBrand(name: 'Beta');

    actAsWorkspaceMember($ownerA, $workspaceA);

    expect(Brand::query()->pluck('id')->all())->toBe([$brandA->id]);

    // Foreign brand IDs 404 through binding (context-scoped).
    $this->actingAs($ownerA)
        ->get(route('brands.show', $brandB))
        ->assertNotFound();
});

it('renders the brand dashboard with metrics', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    actAsWorkspaceMember($owner, $workspace);

    app(SweepRunner::class)->runForBrand($brand);

    $this->actingAs($owner)
        ->get(route('brands.show', $brand))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Brands/Show')
            ->where('metrics.answersCollected', 12)
            ->where('metrics.answersFailed', 0)
            ->has('engines', 2)
            ->has('daily'));
});
