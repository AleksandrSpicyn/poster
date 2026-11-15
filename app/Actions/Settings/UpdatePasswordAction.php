<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\UpdatePasswordData;
use App\Models\User;
use App\Tasks\Users\SaveUser;

readonly class UpdatePasswordAction
{
    public function __construct(private SaveUser $saveUser) {}

    public function run(User $user, UpdatePasswordData $data): void
    {
        $user->password = $data->password;
        $this->saveUser->run($user);
    }
}
