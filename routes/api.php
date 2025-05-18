<?php

use App\Http\Controllers\SunoController;

Route::post('webhook/suno', SunoController::class)->name('webhook.suno');
