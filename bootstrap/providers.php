<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    HorizonServiceProvider::class,
];
