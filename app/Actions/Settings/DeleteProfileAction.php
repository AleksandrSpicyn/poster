<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\AuthGuard;
use App\Models\User;
use App\Tasks\Auth\InvalidateSession;
use App\Tasks\Users\DeleteUser;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;

/**
 * Завершает вход, удаляет текущую учётную запись и инвалидирует сессию.
 * Подтверждение пароля выполняет входной DTO до вызова сценария.
 */
readonly class DeleteProfileAction
{
    public function __construct(
        #[Auth(AuthGuard::WEB->value)] private StatefulGuard $guard,
        private DeleteUser $deleteUser,
        private InvalidateSession $invalidateSession,
    ) {}

    public function run(User $user): void
    {
        $this->guard->logout();
        $this->deleteUser->run($user);
        $this->invalidateSession->run();
    }
}
