<?php

declare(strict_types=1);

use App\Http\Controllers\PublicationRequests\ResolveController;
use Illuminate\Support\Facades\Route;

Route::prefix('series/{series}/episodes/{episode}/revisions/{revision}/assemblies/{assembly}/publications')
    ->whereNumber(['series', 'episode', 'revision', 'assembly'])
    ->middleware(['auth', 'verified', 'can:manageSeries'])
    ->scopeBindings()
    ->group(function (): void {
        Route::post('{publication}/resolve', ResolveController::class)
            ->whereNumber('publication')
            ->name('episodes.publications.resolve');
    });
