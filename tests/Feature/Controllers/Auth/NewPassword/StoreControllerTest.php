<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;

test('верный токен меняет пароль и отправляет событие', function (): void {
    $user = User::factory()->create();

    Event::fake([PasswordReset::class]);

    postJson(route('password.store'), resetPasswordPayload($user, Password::createToken($user)))->assertRedirect(route('login'));

    expect(Hash::check('new-password', $user->refresh()->password))
        ->toBeTrue()
        ->and($user->remember_token)
        ->not->toBeNull();
    Event::assertDispatched(PasswordReset::class, fn(PasswordReset $event): bool => $event->user->getAuthIdentifier() === $user->getAuthIdentifier());

    assertGuest();
});

test('неверный токен не меняет пароль', function (): void {
    $user = User::factory()->create();

    Event::fake([PasswordReset::class]);

    postJson(route('password.store'), resetPasswordPayload($user, 'invalid-token'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
    Event::assertNotDispatched(PasswordReset::class);
});

test('невалидный запрос не меняет пароль', function (array $input, string $field): void {
    $user = User::factory()->create();

    postJson(route('password.store'), resetPasswordPayload($user, Password::createToken($user), $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with([
    'токен обязателен' => [['token' => ''], 'token'],
    'неверный email' => [['email' => 'invalid'], 'email'],
    'короткий пароль' => [['password' => 'short'], 'password'],
    'подтверждение пароля' => [['password_confirmation' => 'different'], 'password'],
]);

/** @param array<array-key, mixed> $overrides
 * @return array<array-key, mixed>
 */
function resetPasswordPayload(User $user, string $token, array $overrides = []): array
{
    return array_replace(['token' => $token, 'email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password'], $overrides);
}

test('авторизованный пользователь перенаправляется в кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('password.store'))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});
