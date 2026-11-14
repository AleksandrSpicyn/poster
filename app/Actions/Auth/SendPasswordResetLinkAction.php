<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Contracts\Auth\PasswordBroker;

readonly class SendPasswordResetLinkAction
{
    public function __construct(private PasswordBroker $passwords) {}

    public function run(string $email): void
    {
        $this->passwords->sendResetLink(['email' => $email]);
    }
}
