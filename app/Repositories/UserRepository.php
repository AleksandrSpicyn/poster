<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\RegisterUserData;
use App\Models\User;

/** @extends EloquentRepository<User> */
readonly class UserRepository extends EloquentRepository
{
    protected function model(): string
    {
        return User::class;
    }

    public function createRegistered(RegisterUserData $data): User
    {
        return User::query()->create(['name' => $data->name, 'email' => $data->email, 'password' => $data->password]);
    }

    public function byEmail(string $ownerEmail): ?User
    {
        return User::query()->where('email', $ownerEmail)->first();
    }

    public function isVerified(int $id): bool
    {
        return User::query()->whereKey($id)->whereNotNull('email_verified_at')->exists();
    }

    public function verifyEmail(User $user): bool
    {
        return $user->markEmailAsVerified();
    }
}
