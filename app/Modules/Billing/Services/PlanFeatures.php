<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Workspace;

/**
 * Resolves the effective feature set for a workspace: its plan's flags on
 * top of safe defaults. Every UI gate and collector check reads from here so
 * plan limits have one enforcement point.
 */
final class PlanFeatures
{
    /** @return array<string, mixed> */
    public function flags(?Workspace $workspace): array
    {
        $plan = $this->plan($workspace);

        return array_merge(
            Plan::defaultFlags(),
            $plan === null ? [] : $plan->feature_flags,
        );
    }

    public function plan(?Workspace $workspace): ?Plan
    {
        if ($workspace === null) {
            return Plan::defaultPlan();
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($subscription === null) {
            return Plan::defaultPlan();
        }

        return $subscription->plan()->first();
    }

    public function allowance(?Workspace $workspace): int
    {
        return (int) ($this->flags($workspace)['answers_per_month'] ?? 150);
    }

    /** @return list<string> */
    public function engines(?Workspace $workspace): array
    {
        /** @var list<string> */
        return array_values(array_filter((array) ($this->flags($workspace)['engines'] ?? [])));
    }

    public function allowsEngine(?Workspace $workspace, string $engine): bool
    {
        return in_array($engine, $this->engines($workspace), true);
    }

    public function flag(?Workspace $workspace, string $key): bool
    {
        return (bool) ($this->flags($workspace)[$key] ?? false);
    }

    public function maxBrands(?Workspace $workspace): int
    {
        return (int) ($this->flags($workspace)['brands'] ?? 1);
    }
}
