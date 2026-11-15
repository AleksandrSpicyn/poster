<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\RegisterUserData;
use App\Enums\AuthGuard;
use App\Tasks\Users\CreateUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Связывает создание учётной записи, событие отправки подтверждения email
 * и вход через web guard; проверка ввода остаётся в DTO.
 */
readonly class RegisterUserAction
{
    public function __construct(
        private CreateUser $createUser,
        #[Auth(AuthGuard::WEB->value)] private StatefulGuard $guard,
        private Dispatcher $events,
    ) {}

    public function run(RegisterUserData $data): void
    {
        $user = $this->createUser->run($data);
        $this->events->dispatch(new Registered($user));
        $this->guard->login($user);
    }
}
