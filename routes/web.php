<?php

use App\Http\Controllers\Posts\ListController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('policy', static function () {
    return Inertia::render('Policy');
})->name('policy');

Route::get('terms', static function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('dashboard', static function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', static function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('posts', ListController::class)->name('posts');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
