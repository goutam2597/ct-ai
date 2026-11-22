<?php

namespace App\Modules\Tenancy\Policies;

use App\Models\User;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function managePeople(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canManagePeople() ?? false;
    }

    public function manageSettings(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canManageWorkspaceSettings() ?? false;
    }

    public function editBilling(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canEditBilling() ?? false;
    }

    public function editResults(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace)?->canEditResults() ?? false;
    }

    /**
     * The membership row is the single source of truth for role checks.
     * Owner is derived from the workspace row too, but membership must agree.
     */
    private function role(User $user, Workspace $workspace): ?WorkspaceRole
    {
        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($membership === null) {
            return null;
        }

        if ($workspace->owner_id === $user->id && $membership->role !== WorkspaceRole::Owner) {
            // Data corruption guard: owner must always hold the Owner membership.
            return null;
        }

        return $membership->role;
    }
}
