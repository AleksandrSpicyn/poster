<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Posts\ListController as PostsListController;
use App\Http\Controllers\Prompts\ListController as PromptsListController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('policy', static fn() => inertia('Policy'))->name('policy');

Route::get('terms', static fn() => inertia('Terms'))->name('terms');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('posts', PostsListController::class)->name('posts');
    Route::get('prompts', PromptsListController::class)->name('prompts');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
