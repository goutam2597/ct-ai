<?php

use App\Models\User;
use App\Modules\ApiPlatform\Models\ApiKey;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use App\Modules\Collection\Services\Sweep\SweepRunner;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * REST API v1 (build plan §6.1 API row): bearer keys act as their creator,
 * foreign brands 404, roles gate the write endpoint, rates carry ranges.
 */
it('rejects missing, malformed and invalid keys', function (): void {
    $this->get('/api/v1/brands')->assertStatus(401);

    $response = app(Kernel::class)->handle(
        Request::create('/api/v1/brands', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer not-a-clot-key',
            'HTTP_ACCEPT' => 'application/json',
        ]),
    );
    expect($response->getStatusCode())->toBe(401);
});

it('lists brands and returns a full overview with Wilson ranges', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    app(SweepRunner::class)->runForBrand($brand);
    $token = makeApiKey($owner, $workspace);

    apiGet($token, '/api/v1/brands')
        ->assertOk()
        ->assertJsonPath('data.0.name', $brand->name);

    apiGet($token, '/api/v1/brands/'.$brand->id)
        ->assertOk()
        ->assertJsonPath('data.overview.days', 30)
        ->assertJsonPath('data.overview.answers', 12)
        ->assertJsonStructure(['data' => ['overview' => ['mention' => ['rate', 'low', 'high']]]]);
});

it('404s brands from another workspace (existence never leaked)', function (): void {
    [$ownerA, $workspaceA, $brandA] = makeTrackingBrand(name: 'Alpha');
    [, , $brandB] = makeTrackingBrand(name: 'Beta');

    $tokenA = makeApiKey($ownerA, $workspaceA);

    apiGet($tokenA, '/api/v1/brands/'.$brandB->id)->assertNotFound();

    // The brand list only contains the key's own workspace.
    apiGet($tokenA, '/api/v1/brands')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Alpha');
});

it('returns per-prompt performance and competitor comparison', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    $token = makeApiKey($owner, $workspace);

    apiGet($token, "/api/v1/brands/{$brand->id}/prompts")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'text', 'engines']]]);

    apiGet($token, "/api/v1/brands/{$brand->id}/competitors")
        ->assertOk()
        ->assertJsonPath('data.you.name', $brand->name)
        ->assertJsonCount(1, 'data.competitors');
});

it('pages answers with filters (per_page capped at 200)', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    app(SweepRunner::class)->runForBrand($brand);
    $token = makeApiKey($owner, $workspace);

    // 12 answers exist; per_page=5 → 5 rows, meta says 12 total.
    apiGet($token, "/api/v1/brands/{$brand->id}/answers?per_page=5")
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 12)
        ->assertJsonPath('meta.per_page', 5);

    // Filter: engine claude (6 answers: 2 prompts × 3 runs).
    apiGet($token, "/api/v1/brands/{$brand->id}/answers?engine=claude")
        ->assertOk()
        ->assertJsonCount(6, 'data');

    // per_page above the cap is clamped to 200, never bigger.
    apiGet($token, "/api/v1/brands/{$brand->id}/answers?per_page=9999")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 200);
});

it('returns opportunities from fixed rules with scores', function (): void {
    [$owner, $workspace, $brand, $competitors] = makeTrackingBrand(runsPerPrompt: 3);
    app(SweepRunner::class)->runForBrand($brand);
    $token = makeApiKey($owner, $workspace);

    // Contoso dominates "What is the best CRM for a small agency?" — create
    // enough answers where the rival is named and you are not (gap ≥ 8 points).
    $brand->engines = ['chatgpt'];
    $brand->save();

    $prompt = Prompt::query()->where('brand_id', $brand->id)->where('text', 'What is the best CRM for a small agency?')->firstOrFail();
    $contoso = $competitors[0];
    $sweep = Sweep::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id, 'brand_id' => $brand->id, 'status' => 'completed',
    ]);

    for ($i = 0; $i < 20; $i++) {
        $cell = SweepCell::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id, 'sweep_id' => $sweep->id, 'prompt_id' => $prompt->id,
            'brand_market_id' => $brand->markets()->firstOrFail()->id, 'engine' => 'chatgpt', 'run_no' => 1,
            'idempotency_key' => 'opp-seed-'.$i, 'status' => 'collected',
        ]);
        $answer = Answer::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id, 'sweep_cell_id' => $cell->id, 'brand_id' => $brand->id,
            'prompt_id' => $prompt->id, 'engine' => 'chatgpt', 'method' => 'demo',
            'status' => AnswerStatus::Collected, 'answer_text' => 'x', 'named_you' => false, 'cited_you' => false,
        ]);

        // Rival named in 16 of 20 answers, you in 0 — gap 80 points.
        if ($i < 16) {
            AnswerMention::withoutWorkspaceScope()->create([
                'workspace_id' => $brand->workspace_id,
                'answer_id' => $answer->id,
                'competitor_id' => $contoso->id,
                'is_you' => false,
                'matched_by' => 'name',
            ]);
        }
    }

    apiGet($token, "/api/v1/brands/{$brand->id}/opportunities")
        ->assertOk()
        ->assertJsonStructure(['data' => [['rule', 'score', 'title', 'evidence']]]);
});

it('gates the write endpoint by the key creator role', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand();
    $ownerToken = makeApiKey($owner, $workspace);

    apiPost($ownerToken, "/api/v1/brands/{$brand->id}/prompts", [
        'prompts' => ['Which CRM integrates best with Slack?'],
    ])->assertStatus(201);

    expect(Prompt::query()->where('brand_id', $brand->id)->count())->toBe(3);

    // Viewer-created key: read OK, write forbidden.
    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id]);
    $viewerToken = makeApiKey($viewer, $workspace);

    apiGet($viewerToken, '/api/v1/brands')->assertOk();

    apiPost($viewerToken, "/api/v1/brands/{$brand->id}/prompts", [
        'prompts' => ['Another question here?'],
    ])->assertStatus(403);

    expect(Prompt::query()->where('brand_id', $brand->id)->count())->toBe(3);
});

it('revoked keys stop working immediately', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    $token = makeApiKey($owner, $workspace);

    apiGet($token, '/api/v1/brands')->assertOk();

    ApiKey::query()->firstOrFail()->delete();

    apiGet($token, '/api/v1/brands')->assertStatus(401);
});
