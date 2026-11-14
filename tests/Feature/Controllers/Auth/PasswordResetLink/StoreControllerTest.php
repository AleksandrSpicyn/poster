<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('восстановление отправляет уведомление владельцу', /** @throws Exception */ function (): void {
    Notification::fake();

    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('авторизованный пользователь перенаправляется в кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('password.email'))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});

test('неизвестный аккаунт получает тот же безопасный ответ без письма', function (): void {
    Notification::fake();

    post(route('password.email'), ['email' => 'missing@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

    Notification::assertNothingSent();
});

test('восстановление отклоняет пустой или неверный email', function (?string $email): void {
    Notification::fake();

    postJson(route('password.email'), ['email' => $email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    Notification::assertNothingSent();
})->with(['отсутствует' => null, 'неверный формат' => 'invalid']);
