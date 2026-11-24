<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\YooKassaWebhookController;
use App\Http\Controllers\SunoController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhook')->name('webhook.')->group(function (): void {
    Route::post('yookassa', YooKassaWebhookController::class)->middleware('throttle:120,1')->name('yookassa');
    Route::post('suno', SunoController::class)->name('suno');
});
