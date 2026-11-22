<?php

use App\Models\User;
use App\Modules\Analytics\Jobs\WebhookDeliveryJob;
use App\Modules\Analytics\Models\AlertChannel;
use App\Modules\Analytics\Models\AlertEvent;
use App\Modules\Analytics\Services\AlertEvaluator;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Alert engine tests: the rules are readable, each alert is sent once, and
 * webhooks are HMAC-signed (the "only real changes" honesty gate, build plan
 * §5.3).
 */
function seedAnswerFor(string $brandName, Carbon $createdAt, bool $named, string $tone = 'positive', ?int $competitorId = null): Answer
{
    static $seed = 0;
    $seed++;

    $brand = Brand::withoutWorkspaceScope()->where('name', $brandName)->firstOrFail();
    $prompt = Prompt::query()->where('brand_id', $brand->id)->firstOrFail();
    $market = $brand->markets()->firstOrFail();

    $sweep = Sweep::withoutWorkspaceScope()
        ->where('brand_id', $brand->id)
        ->first();
    if ($sweep === null) {
        $sweep = Sweep::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id,
            'brand_id' => $brand->id,
            'status' => 'completed',
            'cells_total' => 1,
            'started_at' => $createdAt,
        ]);
    }

    $cell = SweepCell::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id,
        'sweep_id' => $sweep->id,
        'prompt_id' => $prompt->id,
        'brand_market_id' => $market->id,
        'engine' => 'chatgpt',
        'run_no' => 1,
        'idempotency_key' => 'seed-'.$seed,
        'status' => 'collected',
    ]);

    $answer = Answer::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id,
        'sweep_cell_id' => $cell->id,
        'brand_id' => $brand->id,
        'prompt_id' => $prompt->id,
        'engine' => 'chatgpt',
        'method' => 'demo',
        'status' => AnswerStatus::Collected,
        'answer_text' => 'seeded',
        'named_you' => $named,
        'cited_you' => false,
        'tone' => $named ? $tone : null,
    ]);

    // created_at is not fillable: force the historical timestamp the rule
    // windows depend on.
    $answer->forceFill(['created_at' => $createdAt])->saveQuietly();

    if ($competitorId !== null) {
        AnswerMention::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id,
            'answer_id' => $answer->id,
            'competitor_id' => $competitorId,
            'is_you' => false,
            'matched_by' => 'name',
        ]);
    } elseif ($named) {
        AnswerMention::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id,
            'answer_id' => $answer->id,
            'brand_id' => $brand->id,
            'is_you' => true,
            'tone' => $tone,
            'matched_by' => 'name',
        ]);
    }

    return $answer;
}

it('creates a mention-rate-drop alert only when the ranges separate', function (): void {
    [, , $brand] = makeTrackingBrand(name: 'Dropper', competitorNames: []);

    // Before (days 8-14): 60% named, 45 answers. After (last 7 days): 20%, 45 answers.
    for ($i = 0; $i < 45; $i++) {
        seedAnswerFor('Dropper', now()->subDays(10)->subMinutes($i), true);
    }
    for ($i = 0; $i < 36; $i++) {
        seedAnswerFor('Dropper', now()->subDays(2)->subMinutes($i), $i < 9);
    }

    $created = app(AlertEvaluator::class)->evaluateBrand($brand->refresh());

    expect($created)->toBe(1)
        ->and(AlertEvent::query()->where('rule', 'mention_rate_drop')->count())->toBe(1);

    // Re-evaluation sends nothing (dedup key holds).
    expect(app(AlertEvaluator::class)->evaluateBrand($brand->refresh()))->toBe(0);
});

it('stays quiet when ranges overlap (no false alarms)', function (): void {
    [, , $brand] = makeTrackingBrand(name: 'Noisy', competitorNames: []);

    // 45% before, 51% after — overlapping ranges, "no clear change".
    for ($i = 0; $i < 45; $i++) {
        seedAnswerFor('Noisy', now()->subDays(10)->subMinutes($i), $i < 20);
    }
    for ($i = 0; $i < 45; $i++) {
        seedAnswerFor('Noisy', now()->subDays(2)->subMinutes($i), $i < 23);
    }

    expect(app(AlertEvaluator::class)->evaluateBrand($brand->refresh()))->toBe(0)
        ->and(AlertEvent::query()->count())->toBe(0);
});

it('alerts when a rival pulls ahead with identity (competitor mentions)', function (): void {
    [, , $brand, $competitors] = makeTrackingBrand(name: 'Chased', competitorNames: ['Contoso'], runsPerPrompt: 3);
    $contoso = $competitors[0];

    // Before: you 60%, rival 20%. After: you 20%, rival 60% — both with 45 answers.
    for ($i = 0; $i < 45; $i++) {
        $answer = seedAnswerFor('Chased', now()->subDays(10)->subMinutes($i), $i < 27);
        if ($i < 9) {
            AnswerMention::withoutWorkspaceScope()->create([
                'workspace_id' => $answer->workspace_id, 'answer_id' => $answer->id,
                'competitor_id' => $contoso->id, 'is_you' => false, 'matched_by' => 'name',
            ]);
        }
    }
    for ($i = 0; $i < 45; $i++) {
        $answer = seedAnswerFor('Chased', now()->subDays(2)->subMinutes($i), $i < 9);
        if ($i < 27) {
            AnswerMention::withoutWorkspaceScope()->create([
                'workspace_id' => $answer->workspace_id, 'answer_id' => $answer->id,
                'competitor_id' => $contoso->id, 'is_you' => false, 'matched_by' => 'name',
            ]);
        }
    }

    $created = app(AlertEvaluator::class)->evaluateBrand($brand->refresh());

    expect($created)->toBeGreaterThanOrEqual(1)
        ->and(AlertEvent::query()->where('rule', 'rival_pull_ahead')->first()->title)->toContain('Contoso');
});

it('queues signed webhook deliveries to active channels', function (): void {
    [, , $brand] = makeTrackingBrand(name: 'Hooked', competitorNames: []);

    $channel = AlertChannel::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id,
        'type' => 'webhook',
        'target' => 'https://hooks.example/clot',
        'secret' => 'channel-secret',
    ]);

    Http::fake(['hooks.example/*' => Http::response('OK', 200)]);

    // Force the collection-failing rule: 6 failures in the last 24h.
    $prompt = Prompt::query()->where('brand_id', $brand->id)->firstOrFail();
    $sweep = Sweep::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id, 'brand_id' => $brand->id, 'status' => 'completed',
    ]);

    for ($i = 0; $i < 6; $i++) {
        $cell = SweepCell::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id,
            'sweep_id' => $sweep->id,
            'prompt_id' => $prompt->id,
            'brand_market_id' => $brand->markets()->firstOrFail()->id,
            'engine' => 'chatgpt',
            'run_no' => 1,
            'idempotency_key' => 'fail-seed-'.$i,
            'status' => 'failed',
            'failure_reason' => 'seeded failure',
        ]);

        Answer::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id,
            'sweep_cell_id' => $cell->id,
            'brand_id' => $brand->id,
            'prompt_id' => $prompt->id,
            'engine' => 'chatgpt',
            'method' => 'demo',
            'status' => AnswerStatus::Failed,
            'failure_reason' => 'seeded failure',
        ]);
    }

    app(AlertEvaluator::class)->evaluateBrand($brand->refresh());

    WebhookDeliveryJob::dispatchSync($channel->id);

    Http::assertSent(function ($request) {
        $signature = hash_hmac('sha256', json_encode($request->data()), 'channel-secret');

        return $request->hasHeader('X-Clot-Signature', $signature);
    });
});

it('manages alert channels with role gates', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();

    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create([
        'workspace_id' => $workspace->id, 'user_id' => $viewer->id,
    ]);

    actAsWorkspaceMember($viewer, $workspace);
    $this->actingAs($viewer);
    $this->post('/alerts/channels', ['type' => 'webhook', 'target' => 'https://hooks.example/x'])
        ->assertForbidden();

    actAsWorkspaceMember($owner, $workspace);
    $this->actingAs($owner);
    $this->post('/alerts/channels', ['type' => 'webhook', 'target' => 'https://hooks.example/x'])
        ->assertRedirect();

    expect(AlertChannel::query()->count())->toBe(1);
});
