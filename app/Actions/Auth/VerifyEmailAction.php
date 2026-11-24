<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Tasks\Users\VerifyUserEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Меняет статус email и отправляет Verified только при первом подтверждении.
 * Проверка подписанной ссылки и принадлежности пользователю выполняется на HTTP-границе.
 */
readonly class VerifyEmailAction
{
    public function __construct(private VerifyUserEmail $verifyUserEmail, private Dispatcher $events) {}

    public function run(User $user): void
    {
        $verified = DB::transaction(fn(): ?User => $this->verifyUserEmail->run($user));

        if ($verified !== null) {
            $this->events->dispatch(new Verified($verified));
        }
    }
}
