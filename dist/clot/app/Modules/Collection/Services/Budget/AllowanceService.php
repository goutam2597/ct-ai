<?php

namespace App\Modules\Collection\Services\Budget;

use App\Modules\Billing\Services\PlanFeatures;
use App\Modules\Collection\Models\AllowanceLedger;
use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;

/**
 * Monthly answer allowance (ADR-0004). Claims happen BEFORE the provider
 * call; the (workspace, period, claim_key) unique index makes a double claim
 * impossible at the database level, so retries are safe.
 */
final class AllowanceService
{
    public function period(): string
    {
        return now()->format('Y-m');
    }

    /** Answers used this month (claims minus refunds). */
    public function used(?int $workspaceId = null): int
    {
        $workspaceId ??= WorkspaceContext::currentId();

        if ($workspaceId === null) {
            return 0;
        }

        return (int) AllowanceLedger::query()
            ->where('workspace_id', $workspaceId)
            ->where('period', $this->period())
            ->sum('delta') * -1;
    }

    /** Monthly allowance for a workspace: driven by its plan (ADR-0004). */
    public function allowance(?int $workspaceId = null): int
    {
        $workspaceId ??= WorkspaceContext::currentId();

        if ($workspaceId === null) {
            return 0;
        }

        /** @var Workspace|null $workspace */
        $workspace = Workspace::query()->find($workspaceId);

        return (int) app(PlanFeatures::class)->allowance($workspace);
    }

    public function remaining(?int $workspaceId = null): int
    {
        $workspaceId ??= WorkspaceContext::currentId();

        return max(0, $this->allowance($workspaceId) - $this->used($workspaceId));
    }

    /**
     * Claim one answer from the allowance for an idempotency key. Returns true
     * when a NEW claim was made; true-with-existing when this key already has
     * a claim (retry of the same cell — never double-charged).
     *
     * @return array{claimed: bool, existing: bool}
     */
    public function claim(string $claimKey, ?int $workspaceId = null): array
    {
        $workspaceId ??= WorkspaceContext::currentId();

        if ($workspaceId === null) {
            return ['claimed' => false, 'existing' => false];
        }

        $existing = AllowanceLedger::query()
            ->where('workspace_id', $workspaceId)
            ->where('period', $this->period())
            ->where('claim_key', $claimKey)
            ->exists();

        if ($existing) {
            return ['claimed' => true, 'existing' => true];
        }

        return DB::transaction(function () use ($workspaceId, $claimKey): array {
            // Lock the ledger sum so concurrent claims cannot overdraw.
            $used = (int) AllowanceLedger::query()
                ->where('workspace_id', $workspaceId)
                ->where('period', $this->period())
                ->lockForUpdate()
                ->sum('delta') * -1;

            if ($used >= $this->allowance($workspaceId)) {
                return ['claimed' => false, 'existing' => false];
            }

            AllowanceLedger::query()->create([
                'workspace_id' => $workspaceId,
                'period' => $this->period(),
                'delta' => -1,
                'claim_key' => $claimKey,
                'reason' => 'answer',
            ]);

            return ['claimed' => true, 'existing' => false];
        });
    }

    /** Refund a claim after a failed collection (the answer never got paid). */
    public function refund(string $claimKey, ?int $workspaceId = null): void
    {
        $workspaceId ??= WorkspaceContext::currentId();

        AllowanceLedger::query()->create([
            'workspace_id' => $workspaceId,
            'period' => $this->period(),
            'delta' => 1,
            'claim_key' => $claimKey.':refund',
            'reason' => 'refund',
        ]);
    }
}
