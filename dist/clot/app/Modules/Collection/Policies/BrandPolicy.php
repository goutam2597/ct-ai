<?php

namespace App\Modules\Collection\Policies;

use App\Models\User;
use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\WorkspaceContext;

/**
 * Brand access derives from the viewer's membership in the brand's workspace.
 * Foreign brands never reach a policy check: implicit binding is
 * workspace-scoped, so they 404 first (isolation suite).
 */
class BrandPolicy
{
    public function view(User $user, Brand $brand): bool
    {
        return $this->role($user, $brand) !== null;
    }

    public function editResults(User $user, Brand $brand): bool
    {
        return $this->role($user, $brand)?->canEditResults() ?? false;
    }

    public function editSettings(User $user, Brand $brand): bool
    {
        return $this->role($user, $brand)?->canManageWorkspaceSettings() ?? false;
    }

    private function role(User $user, Brand $brand): ?WorkspaceRole
    {
        if ($brand->workspace_id !== WorkspaceContext::currentId()) {
            return null;
        }

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $brand->workspace_id)
            ->first()
            ?->role;
    }
}
