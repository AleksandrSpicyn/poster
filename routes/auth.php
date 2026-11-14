<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ConfirmPassword\ShowController as ShowConfirmPasswordController;
use App\Http\Controllers\Auth\ConfirmPassword\StoreController as StoreConfirmPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\Login\CreateController as CreateLoginController;
use App\Http\Controllers\Auth\Login\StoreController as StoreLoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\NewPassword\CreateController as CreateNewPasswordController;
use App\Http\Controllers\Auth\NewPassword\StoreController as StoreNewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLink\CreateController as CreatePasswordResetLinkController;
use App\Http\Controllers\Auth\PasswordResetLink\StoreController as StorePasswordResetLinkController;
use App\Http\Controllers\Auth\Registration\CreateController as CreateRegistrationController;
use App\Http\Controllers\Auth\Registration\StoreController as StoreRegistrationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::prefix('register')->group(function (): void {
        Route::get('/', CreateRegistrationController::class)->name('register');
        Route::post('/', StoreRegistrationController::class)->name('register.store');
    });

    Route::prefix('login')->group(function (): void {
        Route::get('/', CreateLoginController::class)->name('login');
        Route::post('/', StoreLoginController::class)->name('login.store');
    });

    Route::prefix('forgot-password')->name('password.')->group(function (): void {
        Route::get('/', CreatePasswordResetLinkController::class)->name('request');
        Route::post('/', StorePasswordResetLinkController::class)->name('email');
    });

    Route::prefix('reset-password')->name('password.')->group(function (): void {
        Route::get('{token}', CreateNewPasswordController::class)->name('reset');
        Route::post('/', StoreNewPasswordController::class)->name('store');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::prefix('verify-email')->name('verification.')->group(function (): void {
        Route::get('/', EmailVerificationPromptController::class)->name('notice');
        Route::get('{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verify');
    });

    Route::post('email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::prefix('confirm-password')->group(function (): void {
        Route::get('/', ShowConfirmPasswordController::class)->name('password.confirm');
        Route::post('/', StoreConfirmPasswordController::class)->name('password.confirm.store');
    });

    Route::post('logout', LogoutController::class)->name('logout');
});
