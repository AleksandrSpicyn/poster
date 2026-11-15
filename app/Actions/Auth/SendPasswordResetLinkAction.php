<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\PasswordResetLinkData;
use Illuminate\Contracts\Auth\PasswordBroker;

readonly class SendPasswordResetLinkAction
{
    public function __construct(private PasswordBroker $passwords) {}

    public function run(PasswordResetLinkData $data): void
    {
        $this->passwords->sendResetLink(['email' => $data->email]);
    }
}
