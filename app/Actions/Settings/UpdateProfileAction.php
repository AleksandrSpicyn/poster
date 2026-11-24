<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Actions\Auth\SendEmailVerificationAction;
use App\Data\UpdateProfileData;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;

/**
 * Сохраняет профиль и сбрасывает подтверждение email при смене адреса,
 * чтобы доступ verified снова требовал подтверждения нового адреса.
 */
readonly class UpdateProfileAction
{
    public function __construct(private UserRepository $users, private SendEmailVerificationAction $verification) {}

    public function run(User $user, UpdateProfileData $data): bool
    {
        $changed = DB::transaction(function () use ($user, $data): ?User {
            $profile = $this->users->findLockedOrFail($user->id);
            $profile->fill(['name' => $data->name, 'email' => $data->email]);

            $emailChanged = $profile->isDirty('email');

            if ($emailChanged) {
                $profile->email_verified_at = null;
            }

            $this->users->save($profile);

            return $emailChanged ? $profile : null;
        });

        return $changed !== null && $this->verification->run($changed);
    }
}
