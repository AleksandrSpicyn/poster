<?php

declare(strict_types=1);

use App\Http\Controllers\ProjectCovers\EditController as ProjectCoverEditController;
use App\Http\Controllers\ProjectCovers\ShowController as ProjectCoverShowController;
use App\Http\Controllers\ProjectCovers\StoreController as ProjectCoverStoreController;
use App\Http\Controllers\Projects\ArchiveController as ProjectsArchiveController;
use App\Http\Controllers\Projects\CreateController as ProjectsCreateController;
use App\Http\Controllers\Projects\DestroyController as ProjectsDestroyController;
use App\Http\Controllers\Projects\EditController as ProjectsEditController;
use App\Http\Controllers\Projects\IndexController as ProjectsIndexController;
use App\Http\Controllers\Projects\ShowController as ProjectsShowController;
use App\Http\Controllers\Projects\StoreController as ProjectsStoreController;
use App\Http\Controllers\Projects\UpdateController as ProjectsUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('projects')->name('projects.')->group(function (): void {
    Route::get('', ProjectsIndexController::class)->name('index');
    Route::get('create', ProjectsCreateController::class)->name('create');
    Route::post('', ProjectsStoreController::class)->name('store');
    Route::get('{project}', ProjectsShowController::class)->whereNumber('project')->name('show');
    Route::prefix('{project}/cover')->whereNumber('project')->name('cover.')->group(function (): void {
        Route::get('edit', ProjectCoverEditController::class)->name('edit');
        Route::get('', ProjectCoverShowController::class)->name('show');
        Route::post('', ProjectCoverStoreController::class)->name('store');
    });
    Route::get('{project}/edit', ProjectsEditController::class)->whereNumber('project')->name('edit');
    Route::patch('{project}/archive', ProjectsArchiveController::class)->whereNumber('project')->name('archive');
    Route::patch('{project}', ProjectsUpdateController::class)->whereNumber('project')->name('update');
    Route::delete('{project}', ProjectsDestroyController::class)->whereNumber('project')->name('destroy');
});
