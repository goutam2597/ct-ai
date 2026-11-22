<?php

use App\Modules\Analytics\AnalyticsServiceProvider;
use App\Modules\Billing\BillingServiceProvider;
use App\Modules\Collection\CollectionServiceProvider;
use App\Modules\Ops\OpsServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    CollectionServiceProvider::class,
    BillingServiceProvider::class,
    AnalyticsServiceProvider::class,
    OpsServiceProvider::class,
];
