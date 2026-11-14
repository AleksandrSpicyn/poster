<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use Illuminate\Contracts\Session\Session;

readonly class InvalidateSession
{
    public function __construct(private Session $session) {}

    public function run(): void
    {
        $this->session->invalidate();
        $this->session->regenerateToken();
    }
}
