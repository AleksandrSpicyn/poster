<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Controllers\Horizon\BatchesController;
use App\Policies\OperationsPolicy;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\Http\Controllers\BatchesController as HorizonBatchesController;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HorizonBatchesController::class, BatchesController::class);
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', [OperationsPolicy::class, 'viewQueues']);
    }
}
