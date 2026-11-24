<?php

declare(strict_types=1);

use App\Http\Controllers\Posts\ListController as PostsListController;
use App\Http\Controllers\Posts\ResolveController;
use App\Http\Controllers\Posts\ReviewController;
use App\Http\Controllers\Posts\TiktokCreateController;
use App\Http\Controllers\Posts\TiktokPreviewController;
use App\Http\Controllers\Posts\TiktokStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('posts/tiktok/{channel}')->whereNumber('channel')->name('posts.tiktok.')->group(function (): void {
    Route::get('', TiktokCreateController::class)->name('create');
    Route::post('', TiktokStoreController::class)->name('store');
    Route::get('media/{media}', TiktokPreviewController::class)->whereNumber('media')->name('preview');
});

Route::prefix('posts/requests/{publication}')->whereNumber('publication')->name('posts.requests.')->group(function (): void {
    Route::get('review', ReviewController::class)->name('review');
    Route::post('resolve', ResolveController::class)->name('resolve');
});
Route::get('posts', PostsListController::class)->name('posts');
