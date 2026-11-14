<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\OperationsPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewLogViewer', [OperationsPolicy::class, 'viewLogs']);
    }
}
