<?php

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Enums\BrandAccess;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Facades\Session;

/**
 * Per-request workspace context. Resolved once by SetWorkspaceContext
 * middleware for authenticated web requests; also settable directly in tests
 * and console commands.
 *
 * The current workspace id drives the BelongsToWorkspace global scope, so the
 * context must only be set AFTER authorization has confirmed the user is a
 * member of that workspace.
 */
final class WorkspaceContext
{
    private static ?self $containerInstance = null;

    private ?Workspace $workspace = null;

    private ?Membership $membership = null;

    public static function current(): ?self
    {
        $instance = app()->bound(self::class) ? app(self::class) : null;

        if ($instance !== null) {
            self::$containerInstance = $instance;
        }

        return $instance ?? self::$containerInstance;
    }

    public static function currentId(): ?int
    {
        return self::current()?->workspace?->id;
    }

    public static function currentRole(): ?WorkspaceRole
    {
        return self::current()?->membership?->role;
    }

    public static function currentBrandAccess(): ?BrandAccess
    {
        return self::current()?->membership?->brand_access;
    }

    /**
     * Resolve the active workspace for a user from their stored choice or their
     * first membership. Never grants access: only workspaces the user is a
     * member of can be resolved.
     */
    public function resolveFor(User $user): void
    {
        $membership = $this->membershipFromSession($user)
            ?? $user->memberships()->with('workspace')->orderBy('id')->first();

        if ($membership === null) {
            $this->workspace = null;
            $this->membership = null;

            return;
        }

        $this->membership = $membership;
        $this->workspace = $membership->workspace;
    }

    public function force(Workspace $workspace, Membership $membership): void
    {
        $this->workspace = $workspace;
        $this->membership = $membership;
    }

    public function workspace(): ?Workspace
    {
        return $this->workspace;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function role(): ?WorkspaceRole
    {
        return $this->membership?->role;
    }

    private function membershipFromSession(User $user): ?Membership
    {
        $workspaceId = Session::get($this->sessionKey($user->id));

        if ($workspaceId === null) {
            return null;
        }

        return Membership::query()
            ->where('user_id', $user->id)
            ->where('workspace_id', $workspaceId)
            ->with('workspace')
            ->first();
    }

    private function sessionKey(int $userId): string
    {
        return 'active_workspace.'.$userId;
    }
}
