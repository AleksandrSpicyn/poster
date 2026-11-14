<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('неподтверждённый пользователь видит проверку email', function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('auth/VerifyEmail'));
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('verification.notice');

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});

test('подтверждённый пользователь сразу открывает кабинет', function (): void {
    actingAs(User::factory()->create())
        ->get(route('verification.notice'))
        ->assertRedirect(route('dashboard'));
});
