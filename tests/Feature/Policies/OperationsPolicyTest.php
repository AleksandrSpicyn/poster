<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\OperationsPolicy;

test('операционные панели доступны только подтверждённым разрешённым аккаунтам', function (string $email, bool $verified, bool $logs, bool $queues): void {
    config(['services.admin.email' => 'owner@example.com', 'horizon.allowed_emails' => ['owner@example.com', 'operator@example.com']]);

    $user = User::factory()->create(['email' => $email, 'email_verified_at' => $verified ? now() : null]);
    $policy = app(OperationsPolicy::class);

    expect($policy->viewLogs($user))
        ->toBe($logs)
        ->and($policy->viewQueues($user))
        ->toBe($queues);
})->with([
    'владелец' => ['owner@example.com', true, true, true],
    'оператор очередей' => ['operator@example.com', true, false, true],
    'посторонний' => ['other@example.com', true, false, false],
    'неподтверждённый владелец' => ['owner@example.com', false, false, false],
    'неподтверждённый оператор' => ['operator@example.com', false, false, false],
]);
