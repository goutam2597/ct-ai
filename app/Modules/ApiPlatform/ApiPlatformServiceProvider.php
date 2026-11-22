<?php

namespace App\Modules\ApiPlatform;

use App\Modules\ApiPlatform\Services\ApiRateService;
use Illuminate\Support\ServiceProvider;

final class ApiPlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApiRateService::class);
    }

    public function boot(): void
    {
        //
    }
}
