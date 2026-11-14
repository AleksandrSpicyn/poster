<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        // Local access is handled by Horizon; production has no approved operators yet.
        Gate::define('viewHorizon', static fn(): bool => false);
    }
}
