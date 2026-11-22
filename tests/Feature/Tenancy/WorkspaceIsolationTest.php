<?php

use App\Models\User;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Team;
use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;

/**
 * The tenant-isolation suite. Every workspace-owned model must keep tenants'
 * rows invisible to each other. This test file is a release gate: any new
 * workspace-scoped model joins it here.
 */
it('scopes queries to the current workspace', function (): void {
    [, $workspaceA] = makeWorkspaceWithOwner('Alpha');
    [, $workspaceB] = makeWorkspaceWithOwner('Beta');

    Team::query()->create(['workspace_id' => $workspaceA->id, 'name' => 'Alpha team']);
    Team::query()->create(['workspace_id' => $workspaceB->id, 'name' => 'Beta team']);

    $ownerA = $workspaceA->owner;
    actAsWorkspaceMember($ownerA, $workspaceA);

    $names = Team::query()->orderBy('name')->pluck('name')->all();

    expect($names)->toBe(['Alpha team']);
});

it('refuses to persist workspace-owned rows with no workspace and no context', function (): void {
    // No context set: creating must fail loudly instead of silently landing
    // in the wrong tenant.
    $model = new Team(['name' => 'Orphan team']);

    $model->save();
})->throws(RuntimeException::class);

it('keeps memberships resolvable across workspaces for context switching', function (): void {
    // Memberships are intentionally NOT context-scoped: a user must be able to
    // switch workspaces, so their membership rows stay visible.
    [$ownerA, $workspaceA] = makeWorkspaceWithOwner('Alpha');
    $workspaceB = Workspace::factory()->create(['owner_id' => $ownerA->id, 'name' => 'Beta']);
    Membership::factory()->owner()->create([
        'workspace_id' => $workspaceB->id,
        'user_id' => $ownerA->id,
    ]);

    actAsWorkspaceMember($ownerA, $workspaceA);
    expect($ownerA->memberships()->count())->toBe(2);

    $switchable = Membership::query()
        ->where('user_id', $ownerA->id)
        ->where('workspace_id', $workspaceB->id)
        ->first();

    expect($switchable)->not->toBeNull();
});

it('never exposes another workspace through the context resolution path', function (): void {
    [, $workspaceA] = makeWorkspaceWithOwner('Alpha');
    [, $workspaceB] = makeWorkspaceWithOwner('Beta');

    $intruder = User::factory()->create();

    actAsWorkspaceMember($workspaceA->owner, $workspaceA);
    app(WorkspaceContext::class)->resolveFor($intruder);

    expect(WorkspaceContext::currentId())->toBeNull();
    expect($workspaceB->id)->not->toBe(WorkspaceContext::currentId());
});

it('creates workspaces with exactly one owner membership', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner('Acme');

    $ownerMemberships = Membership::query()
        ->where('workspace_id', $workspace->id)
        ->where('role', WorkspaceRole::Owner->value)
        ->count();

    expect($ownerMemberships)->toBe(1)
        ->and($workspace->owner_id)->toBe($owner->id);
});
