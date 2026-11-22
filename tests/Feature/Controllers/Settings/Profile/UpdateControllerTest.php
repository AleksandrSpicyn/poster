<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;
use function Pest\Laravel\patchJson;

test('изменение email сбрасывает подтверждение', /** @throws Exception */ function (): void {
    Notification::fake();
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    Notification::assertSentTo($user, VerifyEmail::class);
    $user->refresh();

    expect($user->name)
        ->toBe('Test User')
        ->and($user->email)
        ->toBe('test@example.com')
        ->and($user->email_verified_at)
        ->toBeNull();
});

test('сохранение прежнего email не сбрасывает подтверждение', /** @throws Exception */ function (): void {
    Notification::fake();
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    Notification::assertNothingSent();
    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('профиль не принимает email другого пользователя', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $email = $owner->email;

    actingAs($owner)
        ->patchJson(route('profile.update'), ['name' => 'Changed', 'email' => $other->email])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Этот адрес электронной почты уже используется.');

    expect($owner->refresh()->email)
        ->toBe($email)
        ->and($owner->name)->not->toBe('Changed');
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('profile.update');

    patch($url)->assertRedirect(route('login'));
    patchJson($url)->assertUnauthorized();
});

test('профиль отклоняет невалидные поля без изменений', function (array $input, string $field): void {
    $user = User::factory()->create();
    $before = $user->getRawOriginal();

    actingAs($user)
        ->patchJson(route('profile.update'), array_replace(['name' => 'Changed', 'email' => 'changed@example.com'], $input))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($user
        ->refresh()
        ->getRawOriginal())->toEqual($before);
})->with([
    'имя обязательно' => [['name' => ''], 'name'],
    'длинное имя' => [['name' => str_repeat('a', 256)], 'name'],
    'email обязателен' => [['email' => ''], 'email'],
    'формат email' => [['email' => 'invalid'], 'email'],
    'регистр email' => [['email' => 'USER@EXAMPLE.COM'], 'email'],
]);
