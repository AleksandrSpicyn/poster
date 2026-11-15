<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\PasswordResetLinkData;
use Illuminate\Contracts\Auth\PasswordBroker;

/**
 * Передаёт отправку ссылки PasswordBroker; результат не раскрывается клиенту,
 * чтобы HTTP-ответ не сообщал о существовании учётной записи.
 */
readonly class SendPasswordResetLinkAction
{
    public function __construct(private PasswordBroker $passwords) {}

    public function run(PasswordResetLinkData $data): void
    {
        $this->passwords->sendResetLink(['email' => $data->email]);
    }
}
