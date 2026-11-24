<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;
use App\Repositories\UserRepository;

class VerifyUserEmail
{
    public function __construct(private readonly UserRepository $users) {}

    public function run(User $user): ?User
    {
        $current = $this->users->findLockedOrFail($user->id);
        abort_unless($current->getEmailForVerification() === $user->getEmailForVerification(), 403);

        if ($current->hasVerifiedEmail() || ! $this->users->verifyEmail($current)) {
            return null;
        }

        return $current;
    }
}
