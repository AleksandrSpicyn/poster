<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('главная возвращает утверждённый публичный контент', function (): void {
    get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn(Assert $page): Assert => $page
                ->component('Welcome')
                ->missing('homepage')
                ->missing('channelsUrl'),
        );
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
