<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Posts\ListController as PostsListController;
use App\Http\Controllers\Posts\ResolveController;
use App\Http\Controllers\Posts\TiktokCreateController;
use App\Http\Controllers\Posts\TiktokPreviewController;
use App\Http\Controllers\Posts\TiktokStoreController;
use App\Http\Controllers\ProjectCovers\EditController as ProjectCoverEditController;
use App\Http\Controllers\ProjectCovers\ShowController as ProjectCoverShowController;
use App\Http\Controllers\ProjectCovers\StoreController as ProjectCoverStoreController;
use App\Http\Controllers\Projects\CreateController as ProjectsCreateController;
use App\Http\Controllers\Projects\DestroyController as ProjectsDestroyController;
use App\Http\Controllers\Projects\EditController as ProjectsEditController;
use App\Http\Controllers\Projects\IndexController as ProjectsIndexController;
use App\Http\Controllers\Projects\ShowController as ProjectsShowController;
use App\Http\Controllers\Projects\StoreController as ProjectsStoreController;
use App\Http\Controllers\Projects\UpdateController as ProjectsUpdateController;
use App\Http\Controllers\Prompts\ListController as PromptsListController;
use App\Http\Controllers\Prompts\MediaController;
use App\Http\Controllers\PublicationAnalyticsController;
use App\Http\Middleware\VerifyExportOwner;
use App\Http\Middleware\VerifyWorkspaceOwner;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('policy', static fn() => inertia('Policy'))->name('policy');

Route::get('terms', static fn() => inertia('Terms'))->name('terms');

Route::get('prompt-exports/{prompt}/media/{media}', MediaController::class)
    ->scopeBindings()->middleware(['signed', VerifyExportOwner::class])->name('prompts.export');

Route::middleware(['auth', 'verified', VerifyWorkspaceOwner::class])->group(function (): void {
    Route::get('prompts/{prompt}/media/{media}', MediaController::class)->scopeBindings()->name('prompts.media');
    Route::prefix('dashboard')->group(function (): void {
        Route::get('', DashboardController::class)->name('dashboard');
        Route::get('analytics', PublicationAnalyticsController::class)->name('dashboard.analytics');
    });

    Route::prefix('posts/tiktok/{channel}')->whereNumber('channel')->name('posts.tiktok.')->group(function (): void {
        Route::get('', TiktokCreateController::class)->name('create');
        Route::post('', TiktokStoreController::class)->name('store');
        Route::get('media/{media}', TiktokPreviewController::class)->whereNumber('media')->name('preview');
    });

    Route::post('posts/requests/{publication}/resolve', ResolveController::class)->whereNumber('publication')->name('posts.requests.resolve');
    Route::get('posts', PostsListController::class)->name('posts');
    Route::get('prompts', PromptsListController::class)->name('prompts');

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
        Route::patch('{project}', ProjectsUpdateController::class)->whereNumber('project')->name('update');
        Route::delete('{project}', ProjectsDestroyController::class)->whereNumber('project')->name('destroy');
    });
});

require __DIR__ . '/clipping.php';
require __DIR__ . '/channel-management.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/publication-operations.php';
require __DIR__ . '/shot-operations.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/series.php';
require __DIR__ . '/episodes.php';
