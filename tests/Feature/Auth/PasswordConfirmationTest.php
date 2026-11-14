<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('форма подтверждения пароля доступна пользователю', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->get('/confirm-password');

    $response->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page) => $page->component('auth/ConfirmPassword'));
});

test('верный пароль подтверждает сессию', function (): void {
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
