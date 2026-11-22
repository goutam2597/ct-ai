<?php

use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use App\Modules\Collection\Services\ContentBriefService;

function seedBriefAnswer($brand, bool $named, ?int $competitorId = null): void
{
    static $seed = 0;
    $seed++;
    $prompt = Prompt::query()->where('brand_id', $brand->id)->where('text', 'What is the best CRM for a small agency?')->firstOrFail();
    $market = $brand->markets()->firstOrFail();
    $sweep = Sweep::withoutWorkspaceScope()->where('brand_id', $brand->id)->first();
    if ($sweep === null) {
        $sweep = Sweep::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id, 'brand_id' => $brand->id, 'status' => 'completed',
        ]);
    }
    $cell = SweepCell::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id, 'sweep_id' => $sweep->id, 'prompt_id' => $prompt->id,
        'brand_market_id' => $market->id, 'engine' => 'chatgpt', 'run_no' => 1,
        'idempotency_key' => 'brief-'.$seed, 'status' => 'collected',
    ]);
    $answer = Answer::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id, 'sweep_cell_id' => $cell->id, 'brand_id' => $brand->id,
        'prompt_id' => $prompt->id, 'engine' => 'chatgpt', 'method' => 'demo',
        'status' => AnswerStatus::Collected, 'answer_text' => 'Contoso is the best CRM for agencies.',
        'named_you' => $named, 'cited_you' => false,
    ]);
    if ($competitorId !== null) {
        AnswerMention::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id, 'answer_id' => $answer->id,
            'competitor_id' => $competitorId, 'is_you' => false, 'matched_by' => 'name',
        ]);
    }
}

it('generates briefs for losing questions with rival identity', function (): void {
    [$owner, $workspace, $brand, $competitors] = makeTrackingBrand(runsPerPrompt: 3);
    $contoso = $competitors[0];

    // 20 answers: you named in 4 (20%), Contoso named in 16 (80%).
    for ($i = 0; $i < 20; $i++) {
        seedBriefAnswer($brand, $i < 4, $i < 16 ? $contoso->id : null);
    }

    $briefs = app(ContentBriefService::class)->briefsFor($brand);

    expect(count($briefs))->toBeGreaterThanOrEqual(1);

    $first = $briefs[0];
    expect($first['question'])->toBe('What is the best CRM for a small agency?')
        ->and($first['rival_named_instead'])->toBe('Contoso')
        ->and($first['mention_rate'])->toBeLessThan(0.5)
        ->and($first['angle'])->toContain('Contoso')
        ->and($first['answers'])->toBe(20);
});

it('does not generate briefs for winning questions', function (): void {
    [$owner, $workspace, $brand, $competitors] = makeTrackingBrand(runsPerPrompt: 3);
    $contoso = $competitors[0];

    // 20 answers: you named in 18 (90%).
    for ($i = 0; $i < 20; $i++) {
        seedBriefAnswer($brand, $i < 18, $i >= 18 ? $contoso->id : null);
    }

    $briefs = app(ContentBriefService::class)->briefsFor($brand);

    expect($briefs)->toBeEmpty();
});

it('renders the content briefs page', function (): void {
    [$owner, $workspace, $brand, $competitors] = makeTrackingBrand(runsPerPrompt: 3);
    $contoso = $competitors[0];

    for ($i = 0; $i < 15; $i++) {
        seedBriefAnswer($brand, $i < 5, $i < 10 ? $contoso->id : null);
    }

    actAsWorkspaceMember($owner, $workspace);
    $this->actingAs($owner)
        ->get(route('brands.content-briefs', $brand))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Brands/ContentBriefs')
            ->has('briefs'));
});
