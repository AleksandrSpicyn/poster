<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;
use App\Repositories\UserRepository;

class VerifyUserEmail
{
    public function __construct(private readonly UserRepository $users) {}

    public function run(User $user): bool
    {
        return $this->users->verifyEmail($user);
    }
}
