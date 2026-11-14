<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Data\RegisterUserData;
use App\Models\User;

class CreateUser
{
    public function run(RegisterUserData $data): User
    {
        return User::query()->create(['name' => $data->name, 'email' => $data->email, 'password' => $data->password]);
    }
}
