<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;

test('ссылка восстановления открывает форму с токеном и email', function (): void {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    get(route('password.reset', ['token' => $token, 'email' => $user->email]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('auth/ResetPassword')
            ->where('token', $token)
            ->where('email', $user->email));
});

test('авторизованный пользователь перенаправляется в кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('password.reset', ['token' => 'unused-token']))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});

test('токен из query не подменяет токен маршрута', function (): void {
    get(route('password.reset', ['token' => 'route-token']) . '?token=query-token')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('token', 'route-token')
            ->where('email', null));
});
