<?php

namespace App\Modules\Collection\Services\Budget;

use App\Modules\Collection\Models\CostLedger;
use Illuminate\Support\Carbon;

/**
 * Install-wide daily spend cap (ADR-0004, level 1). Checked before every
 * provider call; per-workspace and per-engine caps layer on top in Phase 3.
 */
final class CostGovernor
{
    public function dailyCapUsd(): float
    {
        return (float) config('clot-collection.daily_cap_usd', 25.0);
    }

    /** Spend recorded so far today, across the whole installation. */
    public function spentToday(?Carbon $day = null): float
    {
        $day ??= now()->startOfDay();

        return (float) CostLedger::withoutWorkspaceScope()
            ->where('created_at', '>=', $day)
            ->sum('provider_cost_usd');
    }

    public function canSpend(float $estimated): bool
    {
        return $this->spentToday() + $estimated <= $this->dailyCapUsd();
    }

    /** Rough pre-call estimate used for cap checks (configurable per engine). */
    public function estimateFor(string $engine): float
    {
        /** @var array<string, float> $estimates */
        $estimates = config('clot-collection.estimated_cost_per_answer', []);

        return (float) ($estimates[$engine] ?? 0.01);
    }
}
