<?php

use App\Models\User;
use App\Modules\Tenancy\Models\Workspace;

it('registers a user with a default workspace on the free plan', function (): void {
    $response = $this->post('/register', [
        'name' => 'Nora New',
        'email' => 'nora@buyer.test',
        'password' => 'super-secret-1',
        'password_confirmation' => 'super-secret-1',
        'workspace' => 'Nora HQ',
    ]);

    $response->assertRedirect(route('workspaces.index'));

    $user = User::query()->where('email', 'nora@buyer.test')->firstOrFail();
    $workspace = Workspace::query()->where('name', 'Nora HQ')->firstOrFail();

    expect($user->exists())->toBeTrue()
        ->and($workspace->owner_id)->toBe($user->id)
        ->and($workspace->subscription?->plan->slug)->toBe('free');
});

it('signs in and out', function (): void {
    [$owner] = makeWorkspaceWithOwner();

    $this->post('/login', ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect(route('workspaces.index'));

    $this->assertAuthenticatedAs($owner);

    $this->post('/logout')->assertRedirect(route('home'));
    $this->assertGuest();
});

it('throttles sign-in attempts (5 per minute per email+ip)', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->getBag('default')->first('email'))->toContain('seconds');
});

it('rejects duplicate registration emails', function (): void {
    User::factory()->create(['email' => 'taken@buyer.test']);

    $this->post('/register', [
        'name' => 'Dup',
        'email' => 'taken@buyer.test',
        'password' => 'super-secret-1',
        'password_confirmation' => 'super-secret-1',
    ])->assertSessionHasErrors('email');
});

it('shows the login page to guests instead of the old placeholder', function (): void {
    $this->get('/login')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
    $this->get('/register')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Register'));
});
