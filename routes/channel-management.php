<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Http\Controllers\Channels\CallbackController;
use App\Http\Controllers\Channels\ConnectController;
use App\Http\Controllers\Channels\CreateController;
use App\Http\Controllers\Channels\DestroyController;
use App\Http\Controllers\Channels\DisconnectController;
use App\Http\Controllers\Channels\EditController;
use App\Http\Controllers\Channels\IndexController;
use App\Http\Controllers\Channels\ProviderCallbackController;
use App\Http\Controllers\Channels\StoreController;
use App\Http\Controllers\Channels\TiktokRedirectController;
use App\Http\Controllers\Channels\UpdateController;
use App\Http\Middleware\VerifyWorkspaceOwner;
use Illuminate\Support\Facades\Route;

Route::get('demo/tiktok/callback', TiktokRedirectController::class)->name('channels.tiktok-redirect');

Route::middleware(['auth', 'verified', VerifyWorkspaceOwner::class, 'can:manageChannels'])->prefix('channels')->name('channels.')->group(function (): void {
    Route::get('', IndexController::class)->name('index');
    Route::get('create', CreateController::class)->name('create');
    Route::post('', StoreController::class)->name('store');
    Route::get('oauth/{provider}/callback', ProviderCallbackController::class)->whereIn('provider', array_column(ChannelType::oauthProviders(), 'value'))->block(10, 10)->name('provider-callback');
    Route::prefix('{channel}')->whereNumber('channel')->group(function (): void {
        Route::get('edit', EditController::class)->name('edit');
        Route::patch('', UpdateController::class)->name('update');
        Route::delete('', DestroyController::class)->name('destroy');
        Route::post('connect', ConnectController::class)->block(10, 10)->name('connect');
        Route::get('callback', CallbackController::class)->block(10, 10)->name('callback');
        Route::delete('connection', DisconnectController::class)->name('disconnect');
    });
});
