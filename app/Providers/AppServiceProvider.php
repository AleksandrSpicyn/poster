<?php

declare(strict_types=1);

namespace App\Providers;

use App\Classes\Generators\ImageGenerator\Codex\CodexProvider;
use App\Policies\OperationsPolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Ai::extend('codex', fn(Application $app): CodexProvider => $app->make(CodexProvider::class));

        Gate::define('viewLogViewer', [OperationsPolicy::class, 'viewLogs']);
    }
}
