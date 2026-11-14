<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('выход завершает сессию', function (): void {
    actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    assertGuest();
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('logout');

    post($url)->assertRedirect(route('login'));
    postJson($url)->assertUnauthorized();
});
