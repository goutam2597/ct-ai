<?php

namespace App\Modules\Tenancy\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * There is exactly one owner per workspace; admins run the workspace but
     * cannot change plan/billing; members do the work; viewers read only.
     */
    public function canManagePeople(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageWorkspaceSettings(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canEditBilling(): bool
    {
        return $this === self::Owner;
    }

    public function canEditResults(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Member], true);
    }

    public function canViewBilling(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Member], true);
    }
}
