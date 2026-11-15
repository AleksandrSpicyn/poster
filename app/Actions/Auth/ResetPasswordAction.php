<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\ResetPasswordData;
use App\Models\User;
use App\Tasks\Users\SaveUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Делегирует проверку токена PasswordBroker, затем сохраняет новый пароль
 * и remember token и сообщает об успешном сбросе через событие Laravel.
 */
readonly class ResetPasswordAction
{
    public function __construct(private PasswordBroker $passwords, private SaveUser $saveUser, private Dispatcher $events) {}

    public function run(ResetPasswordData $data): string
    {
        /** @var string $status PasswordBroker returns a translation key. */
        $status = $this->passwords->reset(['email' => $data->email, 'password' => $data->password, 'token' => $data->token], function (User $user) use ($data): void {
            $user->forceFill(['password' => $data->password, 'remember_token' => Str::random(60)]);
            $this->saveUser->run($user);
            $this->events->dispatch(new PasswordReset($user));
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return $status;
    }
}
