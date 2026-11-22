<?php

namespace App\Modules\Ops\Http\Controllers\Admin;

use App\Modules\Billing\Services\PlanFeatures;
use App\Modules\Collection\Models\Answer;
use App\Modules\Ops\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every workspace in the installation (gate 2.3: the operator sees all,
 * customers only their own — enforced by scoping, not by this screen).
 */
final class WorkspacesController extends Controller
{
    public function index(PlanFeatures $features): Response
    {
        $rows = Workspace::query()
            ->with(['owner:id,name,email', 'subscription:id,workspace_id,plan_id,status'])
            ->withCount(['memberships', 'brands' => fn (Builder $q) => $q->withoutGlobalScopes()])
            ->orderBy('id')
            ->get()
            ->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'owner' => $workspace->owner?->email,
                'plan' => $features->plan($workspace)?->name,
                'status' => $workspace->subscription?->status?->value,
                'brands' => $workspace->brands_count,
                'members' => $workspace->memberships_count,
                'answersThisMonth' => (int) (Answer::withoutWorkspaceScope()
                    ->where('workspace_id', $workspace->id)
                    ->where('status', 'collected')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count()),
            ]);

        return Inertia::render('Admin/Workspaces', ['workspaces' => $rows]);
    }
}
