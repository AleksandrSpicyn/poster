<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class Controller
{
    protected function privateFile(string $path): BinaryFileResponse
    {
        return response()->file($path, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    /** @param array<string, string|int> $parameters */
    protected function intendedDashboard(array $parameters = []): RedirectResponse
    {
        return redirect()->intended(route('dashboard', $parameters, absolute: false));
    }

    protected function withStatus(RedirectResponse $redirect, string $status): RedirectResponse
    {
        return $redirect->with('status', $status);
    }
}
