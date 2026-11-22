<?php

namespace App\Modules\Ops\Console;

use App\Modules\Ops\Installer\InstallerService;
use Illuminate\Console\Command;

/**
 * Manual/developer installation route (gate 2.2): after configuring .env by
 * hand and running migrations, `clot:install-seal` performs the same
 * first-account + self-verification + seal as the web installer.
 */
final class InstallSealCommand extends Command
{
    protected $signature = 'clot:install-seal {--name= : First operator name} {--email= : First operator email} {--password= : First operator password}';

    protected $description = 'Create the first operator account, verify the queue, and seal the installer';

    public function handle(InstallerService $installer): int
    {
        if ($installer->isSealed()) {
            $this->error('The installer is already sealed. Nothing to do.');

            return self::FAILURE;
        }

        if (! $installer->schemaMigrated()) {
            $this->error('The database is not migrated. Run "php artisan migrate --force" first.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?? $this->ask('First operator name', 'Operator'));
        $email = (string) ($this->option('email') ?? $this->ask('First operator email'));
        $password = (string) ($this->option('password') ?? $this->secret('First operator password'));

        $user = $installer->createFirstAccount($name, $email, $password);
        $this->info("Created operator account {$user->email}.");

        $verification = $installer->selfVerify();
        $this->info('Queue canary: '.($verification['queue_flowing'] ? 'flowing' : ($verification['queue_pending'] ? 'PENDING — start your queue worker' : 'not flowing')));
        $this->info('Cron entry to add:');
        $this->line('  '.$verification['cron_line']);

        $installer->seal();
        $this->info('Installer sealed. /setup now refuses to run.');

        return self::SUCCESS;
    }
}
