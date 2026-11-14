<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Tasks\Users\VerifyUserEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;

readonly class VerifyEmailAction
{
    public function __construct(private VerifyUserEmail $verifyUserEmail, private Dispatcher $events) {}

    public function run(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if ($this->verifyUserEmail->run($user)) {
            $this->events->dispatch(new Verified($user));
        }
    }
}
