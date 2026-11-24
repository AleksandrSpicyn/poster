<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\Prompts\ListController as PromptsListController;
use App\Http\Controllers\Prompts\MediaController;
use App\Http\Controllers\PublicationAnalyticsController;
use App\Http\Controllers\TermsController;
use App\Http\Middleware\VerifyExportOwner;
use App\Http\Middleware\VerifyWorkspaceOwner;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('policy', PolicyController::class)->name('policy');

Route::get('terms', TermsController::class)->name('terms');

Route::get('prompt-exports/{prompt}/media/{media}', MediaController::class)
    ->scopeBindings()->middleware(['signed', VerifyExportOwner::class])->name('prompts.export');

Route::middleware(['auth', 'verified', VerifyWorkspaceOwner::class])->group(function (): void {
    Route::get('prompts/{prompt}/media/{media}', MediaController::class)->scopeBindings()->name('prompts.media');
    require __DIR__ . '/billing.php';

    Route::prefix('dashboard')->group(function (): void {
        Route::get('', DashboardController::class)->name('dashboard');
        Route::get('analytics', PublicationAnalyticsController::class)->name('dashboard.analytics');
    });

    require __DIR__ . '/posts.php';

    Route::get('prompts', PromptsListController::class)->name('prompts');

    require __DIR__ . '/projects.php';
});

require __DIR__ . '/clipping.php';
require __DIR__ . '/channel-management.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/publication-operations.php';
require __DIR__ . '/shot-operations.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/series.php';
require __DIR__ . '/episodes.php';
