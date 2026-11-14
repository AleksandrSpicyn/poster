<?php

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('форма регистрации доступна гостю', function (): void {
    $response = get('/register');

    $response->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page) => $page->component('auth/Register'));
});

test('регистрация сохраняет пользователя и открывает кабинет', function (): void {
    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    assertAuthenticated();
    \Pest\Laravel\assertDatabaseHas('users', ['name' => 'Test User', 'email' => 'test@example.com']);
    $response->assertRedirect(route('dashboard', absolute: false));
});
