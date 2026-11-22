<?php

use App\Modules\Collection\CollectionServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    CollectionServiceProvider::class,
];
