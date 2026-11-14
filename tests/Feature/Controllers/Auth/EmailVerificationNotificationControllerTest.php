<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test(
    'повторная проверка email отправляется только неподтверждённому пользователю',
    /** @throws Exception */
    function (): void {
        Notification::fake();

        $user = User::factory()
            ->unverified()
            ->create();

        actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    },
);

test('подтверждённый пользователь не получает повторное письмо', function (): void {
    Notification::fake();

    actingAs(User::factory()->create())
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard'));
    Notification::assertNothingSent();
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('verification.send');

    post($url)->assertRedirect(route('login'));
    postJson($url)->assertUnauthorized();
});

test('седьмой запрос проверки email ограничивается без нового письма', /** @throws Exception */ function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    Notification::fake();

    actingAs($user);

    for ($attempt = 0; $attempt < 6; $attempt++) {
        post(route('verification.send'))->assertRedirect();
    }

    post(route('verification.send'))->assertTooManyRequests();

    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});
