<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\ConfirmPasswordData;
use App\Enums\AuthGuard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * Подтверждает пароль текущей учётной записи и отмечает время в сессии
 * для middleware, защищающего чувствительные действия.
 */
readonly class ConfirmPasswordAction
{
    public function __construct(#[Auth(AuthGuard::WEB->value)] private StatefulGuard $guard, private Session $session) {}

    public function run(User $user, ConfirmPasswordData $data): void
    {
        if (! $this->guard->validate(['email' => $user->email, 'password' => $data->password])) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $this->session->put('auth.password_confirmed_at', Carbon::now()->getTimestamp());
    }
}
