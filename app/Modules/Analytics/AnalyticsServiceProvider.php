<?php

namespace App\Modules\Analytics;

use App\Modules\Analytics\Console\AlertsEvaluateCommand;
use Illuminate\Support\ServiceProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->commands([
            AlertsEvaluateCommand::class,
        ]);
    }
}
