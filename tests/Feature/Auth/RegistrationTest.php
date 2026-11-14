<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('форма регистрации доступна гостю', function (): void {
    $response = get('/register');

    $response->assertOk()->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('auth/Register'));
});

test('регистрация сохраняет пользователя и открывает кабинет', function (): void {
    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    assertAuthenticated();
    assertDatabaseHas('users', ['name' => 'Test User', 'email' => 'test@example.com']);
    $response->assertRedirect(route('dashboard', absolute: false));
});
