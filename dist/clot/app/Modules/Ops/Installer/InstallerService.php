<?php

namespace App\Modules\Ops\Installer;

use App\Models\User;
use App\Modules\Ops\Jobs\QueuePingJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Installer state machine (Mono Market gate 2.2):
 *
 *   requirements → database (test + migrate) → first operator account →
 *   self-verification (queue canary) → SEAL.
 *
 * Once sealed, every entry point refuses to run: the web installer returns
 * 404 and the CLI commands abort. Rerunning can never overwrite the first
 * administrator or rewrite production configuration.
 */
final class InstallerService
{
    public function lockPath(): string
    {
        return (string) config('clot-installer.lock_path');
    }

    public function isSealed(): bool
    {
        return File::exists($this->lockPath());
    }

    public function seal(): void
    {
        File::ensureDirectoryExists(dirname($this->lockPath()));
        File::put($this->lockPath(), 'Sealed at '.now()->toIso8601String());
    }

    public function envEditor(): EnvEditor
    {
        return new EnvEditor((string) config('clot-installer.env_path'));
    }

    /**
     * @return array{label: string, ok: bool, hint: string|null}[]
     */
    public function requirements(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP version >= '.config('clot-installer.php_min'),
            'ok' => version_compare(PHP_VERSION, (string) config('clot-installer.php_min'), '>='),
            'hint' => 'Install a newer PHP version in your hosting panel and point the site at it.',
        ];

        foreach ((array) config('clot-installer.extensions', []) as $extension) {
            $checks[] = [
                'label' => 'PHP extension: '.$extension,
                'ok' => extension_loaded($extension),
                'hint' => 'Enable the extension in php.ini (usually a commented "extension=" line).',
            ];
        }

        $checks[] = [
            'label' => 'Vendor directory present',
            'ok' => File::exists(base_path('vendor/autoload.php')),
            'hint' => 'Run composer install --no-dev -o on the server, or upload the vendor/ folder.',
        ];

        $checks[] = [
            'label' => 'storage/ writable',
            'ok' => is_writable(storage_path()),
            'hint' => 'chmod -R ug+w storage (and bootstrap/cache) on the server.',
        ];

        return $checks;
    }

    public function allRequirementsMet(): bool
    {
        foreach ($this->requirements() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Test a database connection WITHOUT touching the app's own connection,
     * then persist the credentials to .env.
     *
     * @param  array{driver: string, database?: string, host?: string, port?: int, name?: string, user?: string, password?: string}  $config
     */
    public function configureDatabase(array $config): void
    {
        $driver = $config['driver'];

        if ($driver === 'sqlite') {
            $path = $config['database'] ?? database_path('database.sqlite');

            if (! File::exists($path) && ! @touch($path)) {
                throw new RuntimeException('Cannot create the SQLite file at '.$path);
            }

            $dsn = 'sqlite:'.$path;

            try {
                $pdo = new \PDO($dsn);
                $pdo->query('SELECT 1');
            } catch (\PDOException $e) {
                throw new RuntimeException('SQLite connection failed: '.$e->getMessage());
            }

            $this->envEditor()->set([
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $path,
                'DB_HOST' => null,
                'DB_PORT' => null,
                'DB_DATABASE_URL' => null,
            ]);
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                $pdo = new \PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s', $config['host'] ?? '127.0.0.1', (int) ($config['port'] ?? 3306), $config['name'] ?? ''),
                    (string) ($config['user'] ?? ''),
                    (string) ($config['password'] ?? ''),
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5],
                );
                $pdo->query('SELECT 1');
            } catch (\PDOException $e) {
                throw new RuntimeException('MySQL connection failed: '.$e->getMessage());
            }

            $this->envEditor()->set([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) ($config['host'] ?? '127.0.0.1'),
                'DB_PORT' => (string) ($config['port'] ?? 3306),
                'DB_DATABASE' => (string) ($config['name'] ?? ''),
                'DB_USERNAME' => (string) ($config['user'] ?? ''),
                'DB_PASSWORD' => (string) ($config['password'] ?? ''),
            ]);
        } else {
            throw new RuntimeException('Unsupported database driver ['.$driver.'].');
        }
    }

    public function runMigrations(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * The first operator account. Refuses if one already exists — the
     * installer must never overwrite an administrator (gate 2.2).
     */
    public function createFirstAccount(string $name, string $email, string $password): User
    {
        if (User::query()->exists()) {
            throw new RuntimeException('An account already exists. The installer will not overwrite it.');
        }

        /** @var User $user */
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_operator' => true,
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    /** Self-verification (gate 2.2): a canary through the queue must complete.
     *
     * @return array{queue_flowing: bool, queue_pending: bool, cron_line: string}
     */
    public function selfVerify(): array
    {
        File::delete((string) config('clot-installer.queue_ping_path'));

        QueuePingJob::dispatch();

        $pending = false;

        if (config('queue.default') !== 'sync') {
            // Non-sync drivers: the canary sits in the queue until a worker
            // runs. Health checks report on it; the installer records it.
            $pending = DB::table('jobs')->count() > 0;
        }

        $flowing = $pending === false && File::exists((string) config('clot-installer.queue_ping_path'));

        return [
            'queue_flowing' => $flowing,
            'queue_pending' => $pending,
            'cron_line' => '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
        ];
    }

    /** Cron heartbeat, refreshed by the scheduled ping command. */
    public function cronHeartbeatFresh(): bool
    {
        $path = (string) config('clot-installer.cron_heartbeat_path');

        if (! File::exists($path)) {
            return false;
        }

        return File::lastModified($path) > now()->subMinutes(5)->getTimestamp();
    }

    public function schemaMigrated(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }
}
