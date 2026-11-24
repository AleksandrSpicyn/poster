<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use Inertia\Response;

final class AppearanceController
{
    public function __invoke(): Response
    {
        return inertia('settings/Appearance');
    }
}
