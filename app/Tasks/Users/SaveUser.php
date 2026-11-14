<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;

class SaveUser
{
    public function run(User $user): void
    {
        $user->save();
    }
}
