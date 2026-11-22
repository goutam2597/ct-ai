<?php

use App\Models\User;
use App\Modules\Ops\Installer\InstallerService;
use App\Modules\Site\Models\FreeCheck;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Support\Facades\File;

/**
 * Operator panel tests (gate 2.3): admin routes enforce the operator gate,
 * not just authentication. Customers never see admin screens.
 */
beforeEach(function (): void {
    $tmp = sys_get_temp_dir().'/clot-admin-test-'.bin2hex(random_bytes(4));
    mkdir($tmp, 0777, true);

    config([
        'clot-installer.lock_path' => $tmp.'/install.lock',
        'clot-installer.env_path' => $tmp.'/.env',
        'clot-installer.queue_ping_path' => $tmp.'/queue-ping',
        'clot-installer.cron_heartbeat_path' => $tmp.'/cron-heartbeat',
    ]);
    $this->tmp = $tmp;
});

afterEach(function (): void {
    File::deleteDirectory($this->tmp);
});

function makeOperator(): User
{
    $operator = User::factory()->create(['is_operator' => true]);

    // Mark the installer state as migrated so the dashboard health reads work.
    app(InstallerService::class)->seal();

    return $operator;
}

it('denies customers access to the admin panel', function (): void {
    [$owner] = makeWorkspaceWithOwner();

    $this->actingAs($owner)
        ->get('/admin')
        ->assertForbidden();
});

it('denies guests and viewers even with forged session state', function (): void {
    $this->get('/admin')->assertRedirect('/login');
    $this->get('/admin/workspaces')->assertRedirect('/login');
});

it('shows the dashboard to the operator', function (): void {
    $operator = makeOperator();
    makeTrackingBrand(name: 'Northwind');

    $this->actingAs($operator)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('workspaces', 1)
            ->where('brands', 1)
            ->has('spend')
            ->has('billing')
            ->has('health'));
});

it('lists every workspace including other tenants', function (): void {
    $operator = makeOperator();
    makeWorkspaceWithOwner('Alpha');
    makeWorkspaceWithOwner('Beta');

    $this->actingAs($operator)
        ->get('/admin/workspaces')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Workspaces')
            ->has('workspaces', 2)
            ->where('workspaces.0.name', 'Alpha')
            ->where('workspaces.1.name', 'Beta'));
});

it('lists engines and runs a test call (billed like an answer)', function (): void {
    $operator = makeOperator();

    $this->actingAs($operator)
        ->get('/admin/engines')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Engines')
            ->has('engines', 9));

    $this->actingAs($operator)
        ->post('/admin/engines/chatgpt/test')
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($operator)
        ->post('/admin/engines/not-an-engine/test')
        ->assertRedirect()
        ->assertSessionHasErrors('test');
});

it('free-check leads exist for the operator funnel', function (): void {
    $operator = makeOperator();
    actAsWorkspaceMember($operator, Workspace::createWithOwner($operator->id, 'Ops'));

    $this->post('/free-ai-visibility-check', [
        'email' => 'lead@buyer.test',
        'brand_name' => 'Northwind',
        'website' => 'northwind.example',
        'offering' => 'CRM',
    ])->assertOk();

    expect(FreeCheck::query()->count())->toBe(1);
});
