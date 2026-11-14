<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('регистрация сохраняет пароль отправляет письмо и открывает кабинет', /** @throws Exception */ function (): void {
    Notification::fake();

    post(route('register.store'), registrationPayload())->assertRedirect(route('dashboard'));

    $user = User::query()->sole();

    assertAuthenticatedAs($user);

    expect($user->name)
        ->toBe('Test User')
        ->and($user->email)
        ->toBe('test@example.com')
        ->and(Hash::check('password', $user->password))
        ->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('невалидная регистрация не создаёт пользователя и не отправляет письма', function (array $input, string $field): void {
    Notification::fake();

    postJson(route('register.store'), registrationPayload($input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    assertGuest();
    assertDatabaseCount('users', 0);

    Notification::assertNothingSent();
})->with([
    'имя обязательно' => [['name' => ''], 'name'],
    'длинное имя' => [['name' => str_repeat('a', 256)], 'name'],
    'неверный email' => [['email' => 'invalid'], 'email'],
    'верхний регистр email' => [['email' => 'DEMO@EXAMPLE.COM'], 'email'],
    'короткий пароль' => [['password' => 'short'], 'password'],
    'подтверждение пароля' => [['password_confirmation' => 'different'], 'password'],
]);

test('повторная регистрация не заменяет аккаунт', function (): void {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $name = $user->name;

    postJson(route('register.store'), registrationPayload(['name' => 'Other']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    assertDatabaseCount('users', 1);

    expect($user->refresh()->name)->toBe($name);
});

/** @param array<array-key, mixed> $overrides
 * @return array<array-key, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return array_replace(['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password'], $overrides);
}

test('авторизованный пользователь перенаправляется в кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('register.store'))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});
