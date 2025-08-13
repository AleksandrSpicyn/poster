<?php

use App\Http\Controllers\LogController;
use App\Http\Controllers\SunoController;

Route::post('webhook/suno', SunoController::class)->name('webhook.suno');
Route::post('webhook/ukassa', LogController::class)->name('webhook.ukassa');
