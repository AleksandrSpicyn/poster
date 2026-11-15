<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\AuthGuard;
use App\Tasks\Auth\InvalidateSession;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;

readonly class LogoutAction
{
    public function __construct(#[Auth(AuthGuard::WEB->value)] private StatefulGuard $guard, private InvalidateSession $invalidateSession) {}

    public function run(): void
    {
        $this->guard->logout();
        $this->invalidateSession->run();
    }
}
