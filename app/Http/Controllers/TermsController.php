<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Response;

final class TermsController
{
    public function __invoke(): Response
    {
        return inertia('Terms');
    }
}
