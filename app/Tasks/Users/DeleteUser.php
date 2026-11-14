<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;

class DeleteUser
{
    public function run(User $user): void
    {
        $user->delete();
    }
}
