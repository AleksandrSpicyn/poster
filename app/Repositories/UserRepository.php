<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Channel;
use App\Models\Chat;
use App\Models\ClippingConnection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

/** @extends EloquentRepository<User> */
readonly class UserRepository extends EloquentRepository
{
    protected function model(): string
    {
        return User::class;
    }

    public function forInvitation(string $email): User
    {
        User::query()->firstOrCreate(['email' => $email], fn(): array => [
            'name' => Str::before($email, '@'),
            'password' => Str::random(64),
        ]);

        return User::query()->where('email', $email)->lockForUpdate()->firstOrFail();
    }

    public function byEmail(string $ownerEmail): ?User
    {
        return User::query()->where('email', $ownerEmail)->first();
    }

    public function ownsWorkspaceData(User $owner): bool
    {
        return Project::query()->where('owner_id', $owner->id)->exists()
            || Channel::query()->where('owner_id', $owner->id)->exists()
            || ClippingConnection::query()->where('owner_id', $owner->id)->exists()
            || Chat::query()->where('owner_id', $owner->id)->exists();
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
