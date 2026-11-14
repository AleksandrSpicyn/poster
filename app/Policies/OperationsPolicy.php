<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Container\Attributes\Config;

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
}
