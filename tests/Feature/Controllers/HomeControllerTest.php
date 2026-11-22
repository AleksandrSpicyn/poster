<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('главная возвращает утверждённый публичный контент', function (): void {
    get(route('home'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->component('Welcome');

            /** @var array<string, mixed> $content */
            $content = require base_path('tests/Fixtures/homepage.php');

            foreach ($content as $key => $value) {
                $page->where($key, $value);
            }
        });
});

test('главная возвращает данные текущего пользователя', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page): Assert => $page
                ->component('Welcome')
                ->where('auth.user.email', $user->email),
        );
});

test('главная ведёт в личный кабинет для подключения канала', function (): void {
    get(route('home'))->assertInertia(fn(Assert $page): Assert => $page->where('channelsUrl', route('channels.index', absolute: false)));
});
