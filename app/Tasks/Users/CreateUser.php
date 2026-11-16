<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Data\RegisterUserData;
use App\Models\User;
use App\Repositories\UserRepository;

class CreateUser
{
    public function __construct(private readonly UserRepository $users) {}

    public function run(RegisterUserData $data): User
    {
        return $this->users->createRegistered($data);
    }
}
