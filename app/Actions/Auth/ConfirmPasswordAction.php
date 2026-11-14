<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

readonly class ConfirmPasswordAction
{
    public function __construct(#[Auth('web')] private StatefulGuard $guard, private Session $session) {}

    public function run(User $user, string $password): void
    {
        if (! $this->guard->validate(['email' => $user->email, 'password' => $password])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $this->session->put('auth.password_confirmed_at', Carbon::now()->getTimestamp());
    }
}
