<?php

namespace App\Modules\Tenancy\Services;

use App\Models\User;
use App\Modules\Tenancy\Enums\BrandAccess;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single sanctioned path for creating a workspace + owner membership
 * atomically. Controllers, commands and tests all go through here so the
 * "exactly one owner per workspace" invariant has one enforcement point.
 */
final class WorkspaceCreator
{
    public function create(int $userId, string $name): Workspace
    {
        $user = User::query()->findOrFail($userId);

        $workspace = DB::transaction(function () use ($user, $name): Workspace {
            $workspace = Workspace::query()->create([
                'owner_id' => $user->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);

            Membership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
                'brand_access' => BrandAccess::All,
                'accepted_at' => now(),
            ]);

            return $workspace;
        });

        return $workspace->refresh();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));

        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (Workspace::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
