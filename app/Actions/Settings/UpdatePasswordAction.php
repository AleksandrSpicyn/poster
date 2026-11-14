<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use App\Tasks\Users\SaveUser;

readonly class UpdatePasswordAction
{
    public function __construct(private SaveUser $saveUser) {}

    public function run(User $user, string $password): void
    {
        $user->password = $password;
        $this->saveUser->run($user);
    }
}
