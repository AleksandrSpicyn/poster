<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\WorkspaceOwner;
use Illuminate\Container\Attributes\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Доступ к логам и очередям задают списки операторов. Серии доступны владельцу рабочего пространства.
 * Все разрешения требуют подтверждённого email, списки берутся из конфигурации.
 */
readonly class OperationsPolicy
{
    /** @param list<string> $queueOperators */
    public function __construct(
        #[Config('services.admin.email')] private string $ownerEmail,
        #[Config('horizon.allowed_emails')] private array $queueOperators,
    ) {}

    public function viewLogs(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->email === $this->ownerEmail;
    }

    public function viewQueues(User $user): bool
    {
        return $user->hasVerifiedEmail() && in_array($user->email, $this->queueOperators, true);
    }

    public function manageChannels(User $user): bool
    {
        return $this->manageSeries($user);
    }

    public function manageSeries(User $user, ?Model $resource = null): bool
    {
        return $user->hasVerifiedEmail() && (! $resource instanceof Model || WorkspaceOwner::id($resource) === $user->id);
    }
}
