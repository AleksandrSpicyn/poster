<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Data\UpdateProfileData;
use App\Models\User;
use App\Tasks\Users\SaveUser;

readonly class UpdateProfileAction
{
    public function __construct(private SaveUser $saveUser) {}

    public function run(User $user, UpdateProfileData $data): void
    {
        $user->fill(['name' => $data->name, 'email' => $data->email]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $this->saveUser->run($user);
    }
}
