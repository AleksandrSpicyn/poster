<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('раздел настроек перенаправляет на профиль', function (): void {
    actingAs(User::factory()->create())
        ->get(route('settings'))
        ->assertRedirect(route('profile.edit'));
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('settings');

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});
