<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Response;

final class PolicyController
{
    public function __invoke(): Response
    {
        return inertia('Policy');
    }
}
