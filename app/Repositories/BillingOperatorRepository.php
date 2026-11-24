<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use DomainException;

final class BillingOperatorRepository
{
    public function authorize(int $operator): void
    {
        $user = User::query()->whereNotNull('email_verified_at')->find($operator);

        if ($user === null || ! in_array($user->email, config()->array('billing.operators'), true)) {
            throw new DomainException('Требуется уполномоченный финансовый оператор.');
        }
    }
}
