<?php

declare(strict_types=1);

use App\Http\Controllers\Shots\AbandonController;
use App\Http\Controllers\Shots\ResolveController;
use App\Http\Controllers\Shots\RetryDownloadController;
use Illuminate\Support\Facades\Route;

Route::prefix('series/{series}/episodes/{episode}/revisions/{revision}/scenes/{scene}/shots/{shot}/takes/{prompt}')
    ->whereNumber(['series', 'episode', 'revision', 'scene', 'shot', 'prompt'])
    ->middleware(['auth', 'verified', 'can:manageSeries'])
    ->scopeBindings()
    ->group(function (): void {
        Route::post('operations/{operation}/resolve', ResolveController::class)->whereNumber('operation')->name('shots.resolve');
        Route::post('operations/{operation}/abandon', AbandonController::class)->whereNumber('operation')->name('shots.abandon');
        Route::post('download-retry', RetryDownloadController::class)->name('shots.download.retry');
    });
