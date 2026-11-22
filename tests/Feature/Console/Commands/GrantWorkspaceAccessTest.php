<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Tests\Support\consoleCommand;

test('повторная выдача заменяет одноразовую ссылку', function (): void {
    Notification::fake();
    $email = 'author@example.com';

    consoleCommand('workspace:grant', ['email' => $email])->assertSuccessful();
    consoleCommand('workspace:grant', ['email' => $email])->assertSuccessful();

    $user = User::query()->sole();
    [$old, $current] = Notification::sent($user, ResetPassword::class)->all();
    assert($old instanceof ResetPassword && $current instanceof ResetPassword);
    expect($user->email)->toBe($email)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(Password::tokenExists($user, $old->token))->toBeFalse();

    post(route('password.store'), ['email' => $email, 'password' => 'new-password', 'password_confirmation' => 'new-password', 'token' => $current->token])
        ->assertRedirect(route('login'));

    expect(Password::tokenExists($user, $current->token))->toBeFalse();
    assertGuest();
});
