<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\Password\EditController as EditPasswordController;
use App\Http\Controllers\Settings\Password\UpdateController as UpdatePasswordController;
use App\Http\Controllers\Settings\Profile\DestroyController as DestroyProfileController;
use App\Http\Controllers\Settings\Profile\EditController as EditProfileController;
use App\Http\Controllers\Settings\Profile\UpdateController as UpdateProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('settings')->group(function (): void {
    Route::redirect('/', '/settings/profile');

    Route::prefix('profile')->name('profile.')->group(function (): void {
        Route::get('/', EditProfileController::class)->name('edit');
        Route::patch('/', UpdateProfileController::class)->name('update');
        Route::delete('/', DestroyProfileController::class)->name('destroy');
    });

    Route::prefix('password')->name('password.')->group(function (): void {
        Route::get('/', EditPasswordController::class)->name('edit');
        Route::put('/', UpdatePasswordController::class)->name('update');
    });

    Route::get('appearance', fn() => inertia('settings/Appearance'))->name('appearance');
});
