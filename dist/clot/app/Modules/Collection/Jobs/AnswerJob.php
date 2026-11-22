<?php

namespace App\Modules\Collection\Jobs;

use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\DTO\CollectRequest;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Enums\CellStatus;
use App\Modules\Collection\Enums\SweepStatus;
use App\Modules\Collection\Enums\Tone;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerCitation;
use App\Modules\Collection\Models\AnswerDailyStat;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;
use App\Modules\Collection\Models\CostLedger;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use App\Modules\Collection\Services\Analysis\CitationScan;
use App\Modules\Collection\Services\Analysis\MentionScan;
use App\Modules\Collection\Services\Budget\AllowanceService;
use App\Modules\Collection\Services\Budget\CostGovernor;
use App\Modules\Collection\Services\CollectorManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * One cell = one provider call = one answer (ADR-0002/0004).
 *
 * Order of operations, enforced:
 *   cap check → allowance claim → collect → store → analyse → cost row.
 * A failed collection refunds its claim and is never counted in any rate.
 */
final class AnswerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** In-sweep retries with backoff (build plan §4.3). */
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $cellId) {}

    public function handle(
        CollectorManager $collectorManager,
        MentionScan $mentionScan,
        CitationScan $citationScan,
        AllowanceService $allowance,
        CostGovernor $governor,
    ): void {
        $cell = SweepCell::withoutWorkspaceScope()->findOrFail($this->cellId);

        if ($cell->status !== CellStatus::Pending) {
            return;
        }

        $sweep = Sweep::withoutWorkspaceScope()->findOrFail($cell->sweep_id);
        $brand = $sweep->brand()->withoutGlobalScopes()->firstOrFail();
        $prompt = $cell->prompt()->withoutGlobalScopes()->firstOrFail();
        $market = $cell->market()->withoutGlobalScopes()->firstOrFail();

        $estimated = $governor->estimateFor($cell->engine);

        // 1. Installation-wide daily cap (ADR-0004 level 1). Systemic failure:
        //    no answer row — the cell retries on the next sweep.
        if (! $governor->canSpend($estimated)) {
            $this->failCell($cell, $sweep, 'daily spend cap reached');

            return;
        }

        // 2. Workspace allowance claim BEFORE the provider call.
        $claim = $allowance->claim('cell:'.$cell->idempotency_key, $cell->workspace_id);

        if (! $claim['claimed']) {
            $this->failCell($cell, $sweep, 'monthly allowance exhausted');

            return;
        }

        // 3. Collect with one in-job retry (collectors must not throw).
        $response = null;
        $attempt = 0;

        while ($attempt < 2) {
            $response = $collectorManager->collect(new CollectRequest(
                prompt: $prompt->text,
                countryCode: $market->country_code,
                languageCode: $market->language_code,
                engine: $cell->engine,
                method: 'demo',
                idempotencyKey: $cell->idempotency_key,
            ));

            if ($response->isCollected()) {
                break;
            }

            $attempt++;
        }

        // 4. Provider failure: store the reason, refund the claim, exclude from rates.
        if (! $response->isCollected()) {
            $allowance->refund('cell:'.$cell->idempotency_key, $cell->workspace_id);

            $this->storeAnswer($cell, $brand, $prompt, AnswerStatus::Failed, null, $response->failureReason);

            $cell->forceFill(['status' => CellStatus::Failed, 'failure_reason' => $response->failureReason])->save();
            $this->bumpSweep($sweep, collected: false);
            $this->bumpDailyStat($cell->workspace_id, $brand->id, $cell->engine, failed: true);

            return;
        }

        // 5. Success: store, analyse, record cost.
        $competitors = $brand->competitors()->get()->all();
        $scan = $mentionScan->scan($brand, (string) $response->answerText, $response, $competitors);

        $answer = $this->storeAnswer(
            $cell,
            $brand,
            $prompt,
            AnswerStatus::Collected,
            $response->answerText,
            null,
            $response,
            $scan->namedYou,
            $scan->citedYou,
            $scan->yourPosition,
            $scan->yourTone?->value,
        );

        foreach ($scan->mentioned as $mention) {
            // Your-mention rows point at the tracked brand; rival rows carry
            // the competitor's own id — rival-pull-ahead alerts need identity.
            AnswerMention::withoutWorkspaceScope()->create([
                'workspace_id' => $cell->workspace_id,
                'answer_id' => $answer->id,
                'brand_id' => $mention['isYou'] ? $brand->id : null,
                'competitor_id' => $mention['brand'] instanceof Competitor ? $mention['brand']->id : null,
                'position' => $mention['isYou'] ? $mention['position'] : null,
                'tone' => $mention['tone'],
                'matched_by' => $mention['matched_by'],
                'is_you' => $mention['isYou'],
            ]);
        }

        foreach ($citationScan->classifyAll($response->citedLinks, $brand, $competitors) as $citation) {
            AnswerCitation::withoutWorkspaceScope()->create([
                'workspace_id' => $cell->workspace_id,
                'answer_id' => $answer->id,
                'url' => $citation['url'],
                'domain' => $citation['domain'],
                'owner_type' => $citation['owner_type'],
            ]);
        }

        $cost = $response->providerCostUsd ?? $estimated;

        if ($cost > 0) {
            CostLedger::withoutWorkspaceScope()->create([
                'workspace_id' => $cell->workspace_id,
                'answer_id' => $answer->id,
                'engine' => $cell->engine,
                'provider_cost_usd' => $cost,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
            ]);
        }

        $this->bumpDailyStat(
            $cell->workspace_id,
            $brand->id,
            $cell->engine,
            failed: false,
            mentionsYou: $scan->namedYou,
            citationsYou: $scan->citedYou,
            namedFirst: $scan->yourPosition === 1,
            position: $scan->yourPosition,
            tone: $scan->yourTone,
        );

        $cell->forceFill(['status' => CellStatus::Collected])->save();
        $this->bumpSweep($sweep, collected: true);
    }

    private function storeAnswer(
        SweepCell $cell,
        Brand $brand,
        Prompt $prompt,
        AnswerStatus $status,
        ?string $text,
        ?string $failureReason,
        ?CollectorResponse $response = null,
        ?bool $namedYou = null,
        ?bool $citedYou = null,
        ?int $position = null,
        ?string $tone = null,
    ): Answer {
        return Answer::withoutWorkspaceScope()->create([
            'workspace_id' => $cell->workspace_id,
            'sweep_cell_id' => $cell->id,
            'brand_id' => $brand->id,
            'prompt_id' => $prompt->id,
            'engine' => $cell->engine,
            'method' => $response !== null && $response->method !== null ? $response->method : 'demo',
            'model' => $response?->modelUsed,
            'status' => $status,
            'answer_text' => $text,
            'failure_reason' => $failureReason,
            'named_you' => $namedYou,
            'cited_you' => $citedYou,
            'position' => $position,
            'tone' => $tone,
            'provider_cost_usd' => $response?->providerCostUsd,
            'input_tokens' => $response?->inputTokens,
            'output_tokens' => $response?->outputTokens,
            'searches_run' => $response?->searchesRun,
            'cited_links' => $response?->citedLinks,
        ]);
    }

    private function failCell(SweepCell $cell, Sweep $sweep, string $reason): void
    {
        $cell->forceFill(['status' => CellStatus::Failed, 'failure_reason' => $reason])->save();
        $this->bumpSweep($sweep, collected: false);
    }

    private function bumpSweep(Sweep $sweep, bool $collected): void
    {
        $column = $collected ? 'cells_collected' : 'cells_failed';

        $counts = Sweep::query()
            ->withoutGlobalScopes()
            ->whereKey($sweep->getKey())
            ->increment($column, 1, ['updated_at' => now()]);

        unset($counts);

        $sweep->refresh();

        if (
            $sweep->cells_total > 0
            && $sweep->cells_collected + $sweep->cells_failed >= $sweep->cells_total
        ) {
            $sweep->forceFill([
                'status' => SweepStatus::Completed,
                'completed_at' => now(),
            ])->save();
        }
    }

    private function bumpDailyStat(
        int $workspaceId,
        int $brandId,
        string $engine,
        bool $failed,
        bool $mentionsYou = false,
        bool $citationsYou = false,
        bool $namedFirst = false,
        ?int $position = null,
        ?Tone $tone = null,
    ): void {
        DB::transaction(function () use ($workspaceId, $brandId, $engine, $failed, $mentionsYou, $citationsYou, $namedFirst, $position, $tone): void {
            $stat = AnswerDailyStat::withoutWorkspaceScope()
                ->where('brand_id', $brandId)
                ->where('engine', $engine)
                ->whereDate('date', now()->toDateString())
                ->first();

            if ($stat === null) {
                $stat = AnswerDailyStat::withoutWorkspaceScope()->create([
                    'workspace_id' => $workspaceId,
                    'brand_id' => $brandId,
                    'engine' => $engine,
                    'date' => now()->startOfDay(),
                    'answers' => 0,
                    'failed' => 0,
                    'mentions_you' => 0,
                    'citations_you' => 0,
                    'named_first' => 0,
                    'position_sum' => 0,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ]);
            }

            // Atomic builder increments (Model::increment is protected in L13).
            $query = AnswerDailyStat::query()
                ->withoutGlobalScopes()
                ->whereKey($stat->getKey());

            $query->increment($failed ? 'failed' : 'answers');

            if ($mentionsYou) {
                $query->increment('mentions_you');
            }
            if ($citationsYou) {
                $query->increment('citations_you');
            }
            if ($namedFirst) {
                $query->increment('named_first');
            }
            if ($position !== null) {
                $query->increment('position_sum', $position);
            }
            if ($tone !== null) {
                $query->increment(match ($tone) {
                    Tone::Positive => 'positive',
                    Tone::Negative => 'negative',
                    default => 'neutral',
                });
            }
        });
    }
}
