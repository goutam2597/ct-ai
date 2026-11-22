<?php

use App\Models\User;
use App\Modules\Tenancy\Models\Membership;

it('denies switching to a workspace the user does not belong to', function (): void {
    [, $workspaceA] = makeWorkspaceWithOwner('Alpha');
    [, $workspaceB] = makeWorkspaceWithOwner('Beta');

    $intruder = User::factory()->create();
    Membership::factory()->create(['workspace_id' => $workspaceA->id, 'user_id' => $intruder->id]);

    $this->actingAs($intruder)
        ->post(route('workspaces.switch', $workspaceB))
        ->assertForbidden();
});

it('allows members to switch to their own workspace', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner('Alpha');

    $this->actingAs($owner)
        ->post(route('workspaces.switch', $workspace))
        ->assertRedirect();

    $this->get(route('workspaces.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Workspaces/Index')
            ->where('workspaces.0.isCurrent', true));
});

it('grants edit rights by role, not by presence', function (): void {
    [, $workspace] = makeWorkspaceWithOwner('Alpha');

    $viewer = User::factory()->create();
    Membership::factory()->viewer()->create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id]);

    $member = User::factory()->create();
    Membership::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $member->id]);

    expect($viewer->can('editResults', $workspace))->toBeFalse()
        ->and($member->can('editResults', $workspace))->toBeTrue();
});

it('denies everything to non-members even when their id is guessed', function (): void {
    [, $workspace] = makeWorkspaceWithOwner('Alpha');

    $outsider = User::factory()->create();

    expect($outsider->can('view', $workspace))->toBeFalse()
        ->and($outsider->can('editResults', $workspace))->toBeFalse()
        ->and($outsider->can('editBilling', $workspace))->toBeFalse();
});

it('only the owner may edit billing', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner('Alpha');

    $admin = User::factory()->create();
    Membership::factory()->admin()->create(['workspace_id' => $workspace->id, 'user_id' => $admin->id]);

    expect($owner->can('editBilling', $workspace))->toBeTrue()
        ->and($admin->can('editBilling', $workspace))->toBeFalse();
});
