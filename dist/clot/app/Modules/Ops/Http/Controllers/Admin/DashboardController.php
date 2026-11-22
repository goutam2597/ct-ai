<?php

namespace App\Modules\Ops\Http\Controllers\Admin;

use App\Models\User;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\CostLedger;
use App\Modules\Collection\Services\Budget\CostGovernor;
use App\Modules\Ops\Http\Controllers\Controller;
use App\Modules\Ops\Installer\InstallerService;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operator dashboard v1: the business numbers (workspaces, plans, answers,
 * spend vs cap) and system health, across the whole installation.
 */
final class DashboardController extends Controller
{
    public function index(CostGovernor $governor, InstallerService $installer): Response
    {
        $monthStart = now()->startOfMonth();

        $subscriptions = Subscription::query()->get();

        $mrr = $subscriptions
            ->filter(fn ($s) => $s->status === SubscriptionStatus::Active)
            ->sum(fn ($s) => (float) $s->plan->monthlyPriceUsd());

        return Inertia::render('Admin/Dashboard', [
            'workspaces' => Workspace::query()->count(),
            'users' => User::query()->count(),
            'brands' => Brand::withoutWorkspaceScope()->count(),
            'answersMonth' => (int) (Answer::withoutWorkspaceScope()->where('status', 'collected')->where('created_at', '>=', $monthStart)->count()),
            'failuresMonth' => (int) (Answer::withoutWorkspaceScope()->where('status', 'failed')->where('created_at', '>=', $monthStart)->count()),
            'spend' => [
                'todayUsd' => round($governor->spentToday(), 2),
                'capUsd' => $governor->dailyCapUsd(),
                'monthUsd' => round((float) CostLedger::withoutWorkspaceScope()
                    ->where('created_at', '>=', $monthStart)->sum('provider_cost_usd'), 2),
            ],
            'billing' => [
                'mrrUsd' => round($mrr, 2),
                'active' => $subscriptions->where('status', SubscriptionStatus::Active)->count(),
                'trialing' => $subscriptions->where('status', SubscriptionStatus::Trialing)->count(),
            ],
            'health' => [
                'cronHeartbeat' => $installer->cronHeartbeatFresh(),
                'queuePing' => File::exists((string) config('clot-installer.queue_ping_path')),
            ],
        ]);
    }
}
