<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Posts\ListController as PostsListController;
use App\Http\Controllers\Projects\CreateController as ProjectsCreateController;
use App\Http\Controllers\Projects\DestroyController as ProjectsDestroyController;
use App\Http\Controllers\Projects\EditController as ProjectsEditController;
use App\Http\Controllers\Projects\IndexController as ProjectsIndexController;
use App\Http\Controllers\Projects\ShowController as ProjectsShowController;
use App\Http\Controllers\Projects\StoreController as ProjectsStoreController;
use App\Http\Controllers\Projects\UpdateController as ProjectsUpdateController;
use App\Http\Controllers\Prompts\ListController as PromptsListController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('policy', static fn() => inertia('Policy'))->name('policy');

Route::get('terms', static fn() => inertia('Terms'))->name('terms');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('posts', PostsListController::class)->name('posts');
    Route::get('prompts', PromptsListController::class)->name('prompts');

    Route::prefix('projects')->name('projects.')->group(function (): void {
        Route::get('', ProjectsIndexController::class)->name('index');
        Route::get('create', ProjectsCreateController::class)->name('create');
        Route::post('', ProjectsStoreController::class)->name('store');
        Route::get('{project}', ProjectsShowController::class)->whereNumber('project')->name('show');
        Route::get('{project}/edit', ProjectsEditController::class)->whereNumber('project')->name('edit');
        Route::patch('{project}', ProjectsUpdateController::class)->whereNumber('project')->name('update');
        Route::delete('{project}', ProjectsDestroyController::class)->whereNumber('project')->name('destroy');
    });
});

require __DIR__ . '/channel-management.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/publication-operations.php';
require __DIR__ . '/shot-operations.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/series.php';
require __DIR__ . '/episodes.php';
require __DIR__ . '/tiktok-demo.php';
