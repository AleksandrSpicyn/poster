<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;

class VerifyUserEmail
{
    public function run(User $user): bool
    {
        return $user->markEmailAsVerified();
    }
}
