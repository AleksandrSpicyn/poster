<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\delete;
use function Pest\Laravel\deleteJson;

test('удаление профиля завершает сессию и удаляет пользователя', /** @throws Exception */ function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    assertGuest();

    expect($user->fresh())->toBeNull();
});

test('неверный пароль запрещает удаление профиля', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('profile.destroy');

    delete($url)->assertRedirect(route('login'));
    deleteJson($url)->assertUnauthorized();
});

test('невалидный пароль не удаляет аккаунт', function (mixed $password): void {
    $user = User::factory()->create();

    actingAs($user)
        ->deleteJson(route('profile.destroy'), ['password' => $password])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect($user->fresh())->not->toBeNull();
})->with(['отсутствует' => null, 'массив' => [['invalid']]]);
