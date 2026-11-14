<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

class GetEmailVerificationStatusAction
{
    public function run(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }
}
