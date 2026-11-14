<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('верный пароль подтверждает сессию', function (): void {
    actingAs(User::factory()->create())
        ->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('auth.password_confirmed_at');
});

test('неверный пароль не подтверждает сессию', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('password.confirm.store');

    post($url)->assertRedirect(route('login'));
    postJson($url)->assertUnauthorized();
});
