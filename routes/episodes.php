<?php

declare(strict_types=1);

use App\Http\Controllers\EpisodeAssemblies;
use App\Http\Controllers\EpisodeAssemblies\AcceptController;
use App\Http\Controllers\EpisodeAssemblies\PublishController;
use App\Http\Controllers\EpisodeMedia\ShowController as EpisodeMediaShowController;
use App\Http\Controllers\Episodes;
use App\Http\Controllers\Episodes\ExportController;
use App\Http\Controllers\Episodes\StoreController;
use App\Http\Controllers\Episodes\UpscaleInputController;
use App\Http\Controllers\EpisodeScripts;
use App\Http\Controllers\EpisodeScripts\ApproveController;
use App\Http\Controllers\EpisodeScripts\GenerateController;
use App\Http\Controllers\EpisodeScripts\ImportSeasonController;
use App\Http\Controllers\Series\StoreAssetController;
use App\Http\Controllers\Shots;
use App\Http\Controllers\Shots\CancelController;
use App\Http\Middleware\VerifyExportOwner;
use App\Http\Middleware\VerifyWorkspaceOwner;
use Illuminate\Support\Facades\Route;

Route::get('upscale-inputs/{operation}/{checksum}', UpscaleInputController::class)
    ->whereNumber('operation')->where('checksum', '[a-f0-9]{64}')->middleware('signed')->name('upscales.input');

Route::get('episode-exports/{assembly}/media/{media}', ExportController::class)
    ->whereNumber(['assembly', 'media'])
    ->scopeBindings()
    ->middleware(['signed', VerifyExportOwner::class])
    ->name('episodes.export');

Route::get('episode-media/{media}', EpisodeMediaShowController::class)
    ->whereNumber('media')
    ->middleware(['auth', 'verified', VerifyWorkspaceOwner::class, 'can:manageSeries'])
    ->name('episodes.media.preview');

Route::post('series/{series}/assets', StoreAssetController::class)
    ->whereNumber('series')->middleware(['auth', 'verified', VerifyWorkspaceOwner::class, 'can:manageSeries'])->name('series.assets.store');

Route::prefix('series/{series}/episodes')->whereNumber('series')->middleware(['auth', 'verified', VerifyWorkspaceOwner::class, 'can:manageSeries'])->scopeBindings()->group(function (): void {
    Route::post('', StoreController::class)->name('episodes.store');

    Route::prefix('{episode}')->whereNumber('episode')->group(function (): void {
        Route::get('', Episodes\ShowController::class)->name('episodes.show');
        Route::post('season/{assetId}', ImportSeasonController::class)->whereNumber('assetId')->name('episodes.scripts.season');
        Route::post('scripts', EpisodeScripts\StoreController::class)->name('episodes.scripts.store');
        Route::post('script-generation', GenerateController::class)->name('episodes.scripts.generate');

        Route::prefix('revisions/{revision}')->whereNumber('revision')->group(function (): void {
            Route::post('approve', ApproveController::class)->name('episodes.scripts.approve');
            Route::post('assemblies', EpisodeAssemblies\StoreController::class)->name('episodes.assemblies.store');

            Route::prefix('assemblies/{assembly}')->whereNumber('assembly')->group(function (): void {
                Route::post('accept', AcceptController::class)->name('episodes.assemblies.accept');
                Route::post('publish', PublishController::class)->name('episodes.assemblies.publish');
            });

            Route::prefix('scenes/{scene}/shots/{shot}')->whereNumber(['scene', 'shot'])->group(function (): void {
                Route::post('generate', Shots\GenerateController::class)->name('shots.generate');
                Route::post('takes/{prompt}/cancel', CancelController::class)->whereNumber('prompt')->name('shots.cancel');
            });
        });
    });
});
