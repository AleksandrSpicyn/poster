<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function withStatus(RedirectResponse $redirect, string $status): RedirectResponse
    {
        return $redirect->with('status', $status);
    }
}
