<?php

declare(strict_types=1);

use App\Http\Controllers\Clipping\AccessController;
use App\Http\Controllers\Clipping\CreateController;
use App\Http\Controllers\Clipping\EditController;
use App\Http\Controllers\Clipping\HistoryController;
use App\Http\Controllers\Clipping\ImportController;
use App\Http\Controllers\Clipping\IndexController;
use App\Http\Controllers\Clipping\PreviewController;
use App\Http\Controllers\Clipping\ResumeController;
use App\Http\Controllers\Clipping\ShowPreviewController;
use App\Http\Controllers\Clipping\SourcesController;
use App\Http\Controllers\Clipping\StoreController;
use App\Http\Controllers\Clipping\UpdateKeyController;
use App\Http\Controllers\Clipping\UpdateScopeController;
use App\Http\Middleware\VerifyWorkspaceOwner;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', VerifyWorkspaceOwner::class])->prefix('clipping')->name('clipping.')->group(function (): void {
    Route::get('', IndexController::class)->name('index');
    Route::get('history', HistoryController::class)->name('history');
    Route::get('create', CreateController::class)->name('create');
    Route::post('', StoreController::class)->name('store');
    Route::prefix('{connection}')->whereNumber('connection')->group(function (): void {
        Route::get('edit', EditController::class)->name('edit');
        Route::get('access', AccessController::class)->name('access');
        Route::patch('scope', UpdateScopeController::class)->name('scope.update');
        Route::patch('key', UpdateKeyController::class)->name('key.update');
        Route::get('sources', SourcesController::class)->name('sources');
        Route::post('sources/{source}/resume', ResumeController::class)->whereNumber('source')->name('sources.resume');
        Route::post('previews', PreviewController::class)->name('preview');
        Route::prefix('previews/{preview}')->whereNumber('preview')->group(function (): void {
            Route::get('', ShowPreviewController::class)->name('preview.show');
            Route::post('confirm', ImportController::class)->name('import');
        });
    });
});
