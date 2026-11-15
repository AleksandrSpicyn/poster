<?php

declare(strict_types=1);

use App\Http\Controllers\CharacterReferences;
use App\Http\Controllers\CharacterReferences\DestroyController;
use App\Http\Controllers\CharacterReferences\ShowController;
use App\Http\Controllers\CharacterRevisions;
use App\Http\Controllers\CharacterRevisions\ApproveController;
use App\Http\Controllers\Characters;
use App\Http\Controllers\Series;
use App\Http\Controllers\Series\ArchiveController;
use App\Http\Controllers\Series\CreateController;
use App\Http\Controllers\Series\EditController;
use App\Http\Controllers\Series\IndexController;
use App\Http\Controllers\Series\StoreController;
use App\Http\Controllers\Series\UpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manageSeries'])->scopeBindings()->group(function (): void {
    Route::get('series', IndexController::class)->name('series.index');
    Route::get('character-references/{revision}/media/{media}', ShowController::class)
        ->whereNumber(['revision', 'media'])
        ->name('characters.references.show');

    Route::prefix('projects/{project}/series')->whereNumber('project')->group(function (): void {
        Route::get('create', CreateController::class)->name('series.create');
        Route::post('', StoreController::class)->name('series.store');

        Route::prefix('{series}')->whereNumber('series')->group(function (): void {
            Route::get('', Series\ShowController::class)->name('series.show');
            Route::get('edit', EditController::class)->name('series.edit');
            Route::patch('', UpdateController::class)->name('series.update');
            Route::post('archive', ArchiveController::class)->name('series.archive');

            Route::prefix('characters')->name('characters.')->group(function (): void {
                Route::post('', Characters\StoreController::class)->name('store');

                Route::prefix('{character}')->whereNumber('character')->group(function (): void {
                    Route::get('', Characters\ShowController::class)->name('show');
                    Route::patch('', Characters\UpdateController::class)->name('update');
                    Route::post('archive', Characters\ArchiveController::class)->name('archive');

                    Route::prefix('revisions')->name('revisions.')->group(function (): void {
                        Route::post('', CharacterRevisions\StoreController::class)->name('store');
                        Route::prefix('{revision}')->whereNumber('revision')->group(function (): void {
                            Route::patch('', CharacterRevisions\UpdateController::class)->name('update');
                            Route::post('approve', ApproveController::class)->name('approve');
                        });
                    });

                    Route::prefix('revisions/{revision}/references')->whereNumber('revision')->name('references.')->group(function (): void {
                        Route::post('', CharacterReferences\StoreController::class)->name('store');
                        Route::delete('{media}', DestroyController::class)->whereNumber('media')->name('destroy');
                    });
                });
            });
        });
    });
});
