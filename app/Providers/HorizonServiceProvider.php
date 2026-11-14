<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\OperationsPolicy;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', [OperationsPolicy::class, 'viewQueues']);
    }
}
