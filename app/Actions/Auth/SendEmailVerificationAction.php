<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

class SendEmailVerificationAction
{
    public function run(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }
}
