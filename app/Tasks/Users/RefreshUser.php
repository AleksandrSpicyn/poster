<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;
use App\Repositories\UserRepository;

readonly class RefreshUser
{
    public function __construct(private UserRepository $users) {}

    public function run(User $user): ?User
    {
        return $this->users->fresh($user);
    }
}
