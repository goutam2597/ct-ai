<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WorkspaceContext::class);
    }

    public function boot(): void
    {
        //
    }
}
