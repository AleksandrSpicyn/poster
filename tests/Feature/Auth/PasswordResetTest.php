<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('форма восстановления доступна гостю', function (): void {
    $response = get('/forgot-password');

    $response->assertOk();
});

test('восстановление отправляет уведомление владельцу', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('ссылка восстановления открывает форму', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (\Illuminate\Auth\Notifications\ResetPassword $notification) {
        $response = get('/reset-password/' . $notification->token);

        $response->assertOk();

        return true;
    });
});

test('верный токен позволяет сменить пароль', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (\Illuminate\Auth\Notifications\ResetPassword $notification) use ($user) {
        $response = post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
