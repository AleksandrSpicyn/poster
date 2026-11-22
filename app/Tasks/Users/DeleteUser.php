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
            throw ValidationException::withMessages(['password' => 'Перед удалением передайте свои проекты, каналы и уведомления.']);
        }
    }

    public function run(User $user): void
    {
        $this->users->delete($user);
    }
}
