<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;

function serialOperator(): User
{
    $user = User::factory()->create();
    $operators = config()->array('horizon.allowed_emails');
    $operators[] = $user->email;
    config(['horizon.allowed_emails' => $operators]);

    return $user;
}
