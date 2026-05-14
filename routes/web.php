<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Posts\ListController as PostsListController;
use App\Http\Controllers\Prompts\ListController as PromptsListController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('policy', static function () {
    return Inertia::render('Policy');
})->name('policy');

Route::get('terms', static function () {
    return Inertia::render('Terms');
})->name('terms');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', static function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('posts', PostsListController::class)->name('posts');
    Route::get('prompts', PromptsListController::class)->name('prompts');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
