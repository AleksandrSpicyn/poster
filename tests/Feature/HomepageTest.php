<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('главная возвращает утверждённый публичный контент', function (): void {
    $response = get('/');

    $response->assertOk();
    $response->assertInertia(function (Assert $page): void {
        $page->component('Welcome');

        /** @var array<string, mixed> $content */
        $content = require __DIR__ . '/../Fixtures/homepage.php';

        foreach ($content as $key => $value) {
            $page->where($key, $value);
        }
    });
});

test('главная возвращает данные текущего пользователя', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn(Assert $page): Assert => $page
            ->component('Welcome')
            ->where('auth.user.email', $user->email),
    );
});
