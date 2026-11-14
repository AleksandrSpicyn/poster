<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;
use App\Tasks\Auth\InvalidateSession;
use App\Tasks\Users\DeleteUser;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Contracts\Auth\StatefulGuard;

readonly class DeleteProfileAction
{
    public function __construct(
        #[Auth('web')] private StatefulGuard $guard,
        private DeleteUser $deleteUser,
        private InvalidateSession $invalidateSession,
    ) {}

    public function run(User $user): void
    {
        $this->guard->logout();
        $this->deleteUser->run($user);
        $this->invalidateSession->run();
    }
}
