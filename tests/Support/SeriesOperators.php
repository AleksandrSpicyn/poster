<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;

function workspaceOwner(): User
{
    return User::query()->where('email', 'studio@example.test')->first()
        ?? User::factory()->create(['email' => 'studio@example.test']);
}

function serialOperator(): User
{
    $user = workspaceOwner();
    $operators = config()->array('horizon.allowed_emails');
    $operators[] = $user->email;
    config(['horizon.allowed_emails' => $operators]);

    return $user;
}
