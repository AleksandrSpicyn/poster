<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('владелец видит настройки пароля', function (): void {
    actingAs(User::factory()->create())
        ->get(route('password.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('settings/Password'));
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('password.edit');

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});
