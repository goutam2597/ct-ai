<?php

namespace App\Modules\Ops;

use App\Modules\Ops\Console\CronPingCommand;
use App\Modules\Ops\Console\InstallSealCommand;
use App\Modules\Ops\Installer\InstallerService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class OpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InstallerService::class);
    }

    public function boot(): void
    {
        $this->commands([
            InstallSealCommand::class,
            CronPingCommand::class,
        ]);

        // Operator gate: the admin panel is separate from every workspace.
        Gate::define('access-admin', function ($user): bool {
            return (bool) ($user->is_operator ?? false);
        });
    }
}
