<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\put;
use function Pest\Laravel\putJson;

test('владелец меняет пароль', /** @throws Exception */ function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('неверный текущий пароль запрещает смену', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->from(route('password.edit'))
        ->put(route('password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors(['current_password' => 'Пароль не подошёл'])
        ->assertRedirect(route('password.edit'));
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('password.update');

    put($url)->assertRedirect(route('login'));
    putJson($url)->assertUnauthorized();
});

test('невалидный новый пароль не меняет учётные данные', function (array $input, string $message): void {
    $user = User::factory()->create();

    actingAs($user)
        ->putJson(route('password.update'), array_replace([
            'current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ], $input))
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', $message);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with([
    'отсутствует' => [['password' => ''], 'Введите новый пароль.'],
    'короткий' => [['password' => 'short', 'password_confirmation' => 'short'], 'Новый пароль должен содержать не менее 8 символов.'],
    'не подтверждён' => [['password_confirmation' => 'different'], 'Подтверждение не совпадает с новым паролем.'],
]);

test('невалидный текущий пароль не меняет учётные данные', function (mixed $password): void {
    $user = User::factory()->create();

    actingAs($user)
        ->putJson(route('password.update'), [
            'current_password' => $password, 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with(['отсутствует' => null, 'массив' => [['invalid']]]);
