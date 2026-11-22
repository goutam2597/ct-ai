<?php

namespace App\Modules\Collection;

use App\Modules\Collection\Console\SweepDueCommand;
use App\Modules\Collection\Contracts\Collector;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Policies\BrandPolicy;
use App\Modules\Collection\Services\CollectorManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class CollectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectorManager::class, function ($app): CollectorManager {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('clot-collection', []);

            /** @var array<string, array{class: class-string<Collector>, config?: array<string, mixed>}> $engines */
            $engines = is_array($config['engines'] ?? null) ? $config['engines'] : [];

            return new CollectorManager($engines);
        });
    }

    public function boot(): void
    {
        Gate::policy(Brand::class, BrandPolicy::class);

        $this->commands([
            SweepDueCommand::class,
        ]);
    }
}
