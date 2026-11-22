<?php

use App\Models\User;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Set the request-level workspace context to a membership the user actually
 * holds. Mirrors what SetWorkspaceContext middleware does after authorization.
 */
function actAsWorkspaceMember(User $user, Workspace $workspace): Membership
{
    $membership = Membership::query()
        ->where('user_id', $user->id)
        ->where('workspace_id', $workspace->id)
        ->firstOrFail();

    app(WorkspaceContext::class)->force($workspace, $membership);

    return $membership;
}

/** Create a workspace + owner membership and return both. */
function makeWorkspaceWithOwner(string $name = 'Acme'): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => $name]);
    Membership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::Owner,
    ]);

    return [$owner, $workspace];
}
