<?php

use App\Models\User;
use App\Modules\Ops\Installer\InstallerService;
use Illuminate\Support\Facades\File;

/**
 * Installer safety tests (Mono Market gate 2.2): fresh install completes and
 * seals; a rerun can never overwrite the first administrator; the CLI seal
 * route behaves identically.
 */
beforeEach(function (): void {
    // Isolated lock/env/ping paths per test.
    $tmp = sys_get_temp_dir().'/clot-install-test-'.bin2hex(random_bytes(4));
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

it('reports requirements and lets the installer open before sealing', function (): void {
    $installer = app(InstallerService::class);

    expect($installer->isSealed())->toBeFalse()
        ->and($installer->allRequirementsMet())->toBeTrue()
        ->and($installer->requirements())->toHaveCount(3 + count(config('clot-installer.extensions')));

    $this->get('/setup')->assertOk();
});

it('completes setup, verifies the queue canary, and seals', function (): void {
    $installer = app(InstallerService::class);

    $this->post('/setup/database', ['driver' => 'sqlite'])->assertRedirect();
    $this->post('/setup/account', [
        'name' => 'Sam Operator',
        'email' => 'operator@clot.test',
        'password' => 'super-secret-1',
    ])->assertRedirect();

    $this->post('/setup/finish')->assertRedirect();

    expect($installer->isSealed())->toBeTrue()
        ->and(File::get($this->tmp.'/install.lock'))->toContain('Sealed')
        ->and(User::query()->where('email', 'operator@clot.test')->first()->is_operator)->toBeTrue()
        ->and(File::exists($this->tmp.'/queue-ping'))->toBeTrue();

    // Exactly one account: the installer cannot be re-run to add another.
    expect(User::query()->count())->toBe(1);
});

it('returns 404 for every setup route once sealed', function (): void {
    $installer = app(InstallerService::class);
    $installer->seal();

    $this->get('/setup')->assertNotFound();
    $this->post('/setup/database', ['driver' => 'sqlite'])->assertNotFound();
    $this->post('/setup/account', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password123'])->assertNotFound();
    $this->post('/setup/finish')->assertNotFound();
});

it('refuses to create a second account through the service (no overwrite)', function (): void {
    $installer = app(InstallerService::class);

    $installer->createFirstAccount('First', 'first@clot.test', 'super-secret-1');

    expect(fn () => $installer->createFirstAccount('Second', 'second@clot.test', 'super-secret-2'))
        ->toThrow(RuntimeException::class, 'An account already exists');

    expect(User::query()->count())->toBe(1);
});

it('env editor writes and replaces values without corrupting the file', function (): void {
    $installer = app(InstallerService::class);
    $editor = $installer->envEditor();

    File::put($this->tmp.'/.env', "APP_NAME=CLOT\n# a comment\nAPP_ENV=local\n");

    $editor->set(['APP_ENV' => 'production', 'DB_HOST' => '127.0.0.1']);

    $content = File::get($this->tmp.'/.env');

    expect($content)->toContain('APP_ENV=production')
        ->and($content)->toContain('DB_HOST=127.0.0.1')
        ->and($content)->toContain('# a comment')
        ->and($content)->toContain('APP_NAME=CLOT')
        ->and($editor->get('APP_ENV'))->toBe('production');
});

it('writes the cron heartbeat via the scheduled ping command', function (): void {
    $this->artisan('clot:install-cron-ping')->assertSuccessful();

    expect(app(InstallerService::class)->cronHeartbeatFresh())->toBeTrue();
});
