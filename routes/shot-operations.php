<?php

declare(strict_types=1);

use App\Http\Controllers\Shots\AbandonController;
use App\Http\Controllers\Shots\ApproveFrameController;
use App\Http\Controllers\Shots\ResolveController;
use App\Http\Controllers\Shots\RetryDownloadController;
use App\Http\Middleware\VerifyWorkspaceOwner;
use Illuminate\Support\Facades\Route;

Route::prefix('series/{series}/episodes/{episode}/revisions/{revision}/scenes/{scene}/shots/{shot}/takes/{prompt}')
    ->whereNumber(['series', 'episode', 'revision', 'scene', 'shot', 'prompt'])
    ->middleware(['auth', 'verified', VerifyWorkspaceOwner::class, 'can:manageSeries'])
    ->scopeBindings()
    ->group(function (): void {
        Route::prefix('operations/{operation}')->whereNumber('operation')->name('shots.')->group(function (): void {
            Route::post('approve-frame', ApproveFrameController::class)->name('frame.approve');
            Route::post('resolve', ResolveController::class)->name('resolve');
            Route::post('abandon', AbandonController::class)->name('abandon');
        });
        Route::post('download-retry', RetryDownloadController::class)->name('shots.download.retry');
    });
