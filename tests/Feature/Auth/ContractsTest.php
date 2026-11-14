<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;

test('регистрация отклоняет невалидные данные без пользователя и уведомлений', function (string $field, string $value): void {
    Notification::fake();
    $payload = ['name' => 'Demo', 'email' => 'demo@example.com', 'password' => 'password', 'password_confirmation' => 'password'];
    $payload[$field] = $value;

    postJson(route('register'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field === 'password_confirmation' ? 'password' : $field);

    assertGuest();
    assertDatabaseCount('users', 0);
    Notification::assertNothingSent();
})->with([
    ['name', ''], ['email', 'invalid'], ['email', 'DEMO@EXAMPLE.COM'],
    ['password', 'short'], ['password_confirmation', 'different'],
]);

test('повторная регистрация email не заменяет аккаунт', function (): void {
    $user = User::factory()->create(['email' => 'demo@example.com']);

    postJson(route('register'), ['name' => 'Other', 'email' => $user->email, 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    assertDatabaseCount('users', 1);
    expect($user->refresh()->name)->not->toBe('Other');
});

test('профиль не принимает email другого пользователя', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $email = $owner->email;

    actingAs($owner)->patchJson(route('profile.update'), ['name' => 'Changed', 'email' => $other->email])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($owner->refresh()->email)->toBe($email)->and($owner->name)->not->toBe('Changed');
});

test('неверный токен восстановления не меняет пароль', function (): void {
    $user = User::factory()->create();

    postJson(route('password.store'), ['token' => 'invalid-token', 'email' => $user->email,
        'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('подтверждённый пользователь не получает повторное письмо', function (): void {
    Notification::fake();
    actingAs(User::factory()->create())->post(route('verification.send'))->assertRedirect(route('dashboard'));
    Notification::assertNothingSent();
});
