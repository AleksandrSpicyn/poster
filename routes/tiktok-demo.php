<?php

declare(strict_types=1);

use App\Http\Controllers\TiktokDemo\CallbackController;
use App\Http\Controllers\TiktokDemo\ConnectController;
use App\Http\Controllers\TiktokDemo\DirectPostController;
use App\Http\Controllers\TiktokDemo\DraftController;
use App\Http\Controllers\TiktokDemo\ShowController;
use App\Http\Middleware\AuthenticateTiktokDemo;
use Illuminate\Support\Facades\Route;

Route::prefix('demo/tiktok')->name('tiktok-demo.')->middleware(['throttle:60,1', AuthenticateTiktokDemo::class])->group(function (): void {
    Route::get('callback', CallbackController::class)->name('callback')->block(90);
    Route::get('authors/{author?}', ShowController::class)->name('show')->block(90);
    Route::post('authors/{author}/connect', ConnectController::class)->name('connect')->block(90);
    Route::post('authors/{author}/direct', DirectPostController::class)->name('direct')->block(100);
    Route::post('authors/{author}/draft', DraftController::class)->name('draft')->block(90);
});
