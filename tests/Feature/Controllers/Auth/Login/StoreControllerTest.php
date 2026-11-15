<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('верный пароль открывает кабинет и создаёт сессию владельца', function (): void {
    $user = User::factory()->create();

    post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});

test('неверный пароль не создаёт сессию', function (): void {
    $user = User::factory()->create();

    postJson(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    assertGuest();
});

test('шестая попытка блокирует даже верный пароль', function (): void {
    $user = User::factory()->create();

    Event::fake([Lockout::class]);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        postJson(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    assertGuest();

    Event::assertDispatched(Lockout::class);
});

test('вход отклоняет невалидные данные', function (array $input, array $errors): void {
    postJson(route('login.store'), $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);

    assertGuest();
})->with([
    'пустой запрос' => [[], ['email', 'password']],
    'неверный email' => [['email' => 'invalid', 'password' => 'password'], ['email']],
    'неизвестный аккаунт' => [['email' => 'missing@example.com', 'password' => 'password'], ['email']],
]);

test('авторизованный пользователь перенаправляется в кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('login.store'))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});

test('запоминание входа создаёт cookie владельца', function (bool|int|string $remember): void {
    $user = User::factory()->create();

    post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => $remember])
        ->assertRedirect(route('dashboard'))
        ->assertCookie(auth()->guard('web')->getRecallerName());

    assertAuthenticatedAs($user);
    expect($user->refresh()->remember_token)->not->toBeNull();
})->with([true, 1, '1']);

test('невалидное запоминание не создаёт сессию', function (): void {
    $user = User::factory()->create();

    postJson(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => ['invalid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('remember');

    assertGuest();
});
