<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    public function __invoke(Request $request): void
    {
        Log::info('Webhook UKassa received', $request->all());
    }
}
