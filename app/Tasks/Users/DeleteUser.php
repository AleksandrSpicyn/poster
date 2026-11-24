<?php

declare(strict_types=1);

namespace App\Tasks\Users;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __construct(private readonly UserRepository $users) {}

    public function run(User $user): void
    {
        $user = $this->users->findLockedOrFail($user->id);

        if ($this->users->ownsWorkspaceData($user)) {
            throw ValidationException::withMessages(['password' => 'Перед удалением передайте свои проекты, каналы и уведомления.']);
        }

        if ($this->users->hasRetainedHistory($user)) {
            throw ValidationException::withMessages(['password' => 'Удаление недоступно: с аккаунтом связаны записи биллинга или история выпуска.']);
        }

        $this->users->delete($user);
    }
}
