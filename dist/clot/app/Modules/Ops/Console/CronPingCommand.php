<?php

namespace App\Modules\Ops\Console;

use App\Modules\Ops\Installer\InstallerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class CronPingCommand extends Command
{
    protected $signature = 'clot:install-cron-ping';

    protected $description = 'Write the cron heartbeat (scheduled every minute)';

    public function handle(InstallerService $installer): int
    {
        $path = (string) config('clot-installer.cron_heartbeat_path');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, now()->toIso8601String());

        return self::SUCCESS;
    }
}
