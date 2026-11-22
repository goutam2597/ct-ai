<?php

namespace App\Modules\Collection\Services\Sweep;

use App\Modules\Collection\Enums\CellStatus;
use App\Modules\Collection\Enums\SweepStatus;
use App\Modules\Collection\Jobs\AnswerJob;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use App\Modules\Collection\Services\CollectorManager;

/**
 * Creates a sweep for one brand: every active prompt × chosen engine × run ×
 * market, as idempotent cells (ADR-0002/0004). Cells that already exist —
 * from any earlier sweep on the same day — are skipped, so a repeated sweep
 * only fills in what is missing and the same answer is never paid twice.
 */
final class SweepRunner
{
    public function __construct(private readonly CollectorManager $collectorManager) {}

    public function runForBrand(Brand $brand): Sweep
    {
        $sweep = Sweep::query()->create([
            'workspace_id' => $brand->workspace_id,
            'brand_id' => $brand->id,
            'status' => SweepStatus::Running,
            'started_at' => now(),
        ]);

        $cellCount = 0;
        $today = now()->toDateString();

        foreach ($brand->markets as $market) {
            $prompts = $brand->prompts()->where('status', 'active')->orderBy('id')->get();

            foreach ($prompts as $prompt) {
                foreach ($brand->engines as $engine) {
                    if (! $this->collectorManager->has((string) $engine)) {
                        continue;
                    }

                    for ($run = 1; $run <= $brand->runs_per_prompt; $run++) {
                        $key = hash('sha256', implode('|', [
                            $prompt->id, $engine, $market->id, $today, $run,
                        ]));

                        $cell = SweepCell::withoutWorkspaceScope()->firstOrCreate(
                            ['idempotency_key' => $key],
                            [
                                'workspace_id' => $brand->workspace_id,
                                'sweep_id' => $sweep->id,
                                'prompt_id' => $prompt->id,
                                'brand_market_id' => $market->id,
                                'engine' => (string) $engine,
                                'run_no' => $run,
                                'status' => CellStatus::Pending,
                            ],
                        );

                        // Another sweep today already owns this cell.
                        if ($cell->sweep_id !== $sweep->id) {
                            continue;
                        }

                        $cellCount++;
                        AnswerJob::dispatch($cell->id);
                    }
                }
            }
        }

        $sweep->forceFill(['cells_total' => $cellCount])->save();

        // Sync-queue case: jobs already ran while cells_total was still 0, so
        // the completion check inside AnswerJob never fired. Re-check here.
        $sweep->refresh();

        if ($cellCount === 0 || $sweep->cells_collected + $sweep->cells_failed >= $cellCount) {
            $sweep->forceFill([
                'status' => SweepStatus::Completed,
                'completed_at' => now(),
            ])->save();
        }

        $brand->forceFill([
            'next_sweep_at' => now()->addDays($brand->frequency->intervalDays()),
        ])->save();

        return $sweep->refresh();
    }
}
