<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Auth\SendEmailVerificationAction;
use App\Data\UpdateProfileData;
use App\Models\User;
use App\Tasks\Users\SaveUser;

/**
 * Сохраняет профиль и сбрасывает подтверждение email при смене адреса,
 * чтобы доступ verified снова требовал подтверждения нового адреса.
 */
readonly class UpdateProfileAction
{
    public function __construct(private SaveUser $saveUser, private SendEmailVerificationAction $verification) {}

    public function run(User $user, UpdateProfileData $data): bool
    {
        $user->fill(['name' => $data->name, 'email' => $data->email]);

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $this->saveUser->run($user);

        return $emailChanged && $this->verification->run($user);
    }
}
