<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('главная возвращает утверждённый публичный контент', function (): void {
    $response = get('/');

    $response->assertOk();
    $response->assertInertia(function (Assert $page): void {
        $page->component('Welcome');

        foreach (require __DIR__ . '/../Fixtures/homepage.php' as $key => $value) {
            $page->where($key, $value);
        }
    });
});

test('главная возвращает данные текущего пользователя', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn(Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.email', $user->email),
    );
});
