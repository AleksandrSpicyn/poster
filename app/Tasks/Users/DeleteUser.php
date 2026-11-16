<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;
use App\Repositories\UserRepository;

class DeleteUser
{
    public function __construct(private readonly UserRepository $users) {}

    public function run(User $user): void
    {
        $this->users->delete($user);
    }
}
