<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('подписанная ссылка подтверждает email', function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    Event::fake([Verified::class]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    actingAs($user)
        ->get($url)
        ->assertRedirect(route('dashboard', ['verified' => 1]));

    expect($user
        ->refresh()
        ->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class, fn(Verified $event): bool => $event->user->getEmailForVerification() === $user->email);
});

test('неверный hash не подтверждает email', function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    actingAs($user)
        ->get($verificationUrl)
        ->assertForbidden();

    expect($user
        ->refresh()
        ->hasVerifiedEmail())->toBeFalse();
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('verification.verify', ['id' => 1, 'hash' => sha1('guest@example.com')]);

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});

test('ссылка другого пользователя или просроченная подпись не подтверждают email', function (bool $expired): void {
    $user = User::factory()
        ->unverified()
        ->create();
    $url = URL::temporarySignedRoute('verification.verify', $expired ? now()->subMinute() : now()->addHour(), [
        'id' => $expired ? $user->id : 0, 'hash' => sha1($user->email),
    ]);

    Event::fake([Verified::class]);

    actingAs($user)
        ->get($url)
        ->assertForbidden();

    expect($user
        ->refresh()
        ->hasVerifiedEmail())->toBeFalse();
    Event::assertNotDispatched(Verified::class);
})->with(['чужой id' => false, 'просроченная подпись' => true]);

test('повторное подтверждение не отправляет событие снова', function (): void {
    $user = User::factory()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    Event::fake([Verified::class]);

    actingAs($user)
        ->get($url)
        ->assertRedirect(route('dashboard', ['verified' => 1]));

    Event::assertNotDispatched(Verified::class);
});
