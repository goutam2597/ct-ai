<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Console\BillingRenewCommand;
use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\PlanFeatures;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanFeatures::class);

        $this->app->singleton(BillingService::class, function ($app): BillingService {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('clot-billing', []);

            /** @var array<string, array{class: class-string<PaymentGateway>, label?: string}> $gateways */
            $gateways = is_array($config['gateways'] ?? null) ? $config['gateways'] : [];

            $resolved = [];

            foreach ($gateways as $key => $definition) {
                $resolved[$key] = $app->make($definition['class']);
            }

            return new BillingService($resolved);
        });
    }

    public function boot(): void
    {
        $this->commands([
            BillingRenewCommand::class,
        ]);
    }
}
