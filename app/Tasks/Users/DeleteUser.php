<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __construct(private readonly UserRepository $users) {}

    public function ensureCanDelete(User $user): void
    {
        if ($this->users->ownsWorkspaceData($user)) {
            throw ValidationException::withMessages(['password' => 'Transfer your projects, channels and notifications before deleting this account.']);
        }
    }

    public function run(User $user): void
    {
        $this->users->delete($user);
    }
}
