<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('форма подтверждения пароля доступна пользователю', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('password.confirm'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('auth/ConfirmPassword'));
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('password.confirm');

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});
