<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\actingAs;

test('форма подтверждения пароля доступна пользователю', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->get('/confirm-password');

    $response->assertOk()->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('auth/ConfirmPassword'));
});

test('верный пароль подтверждает сессию', /** @throws Exception */ function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('неверный пароль не подтверждает сессию', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
