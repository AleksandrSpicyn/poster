<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('гость перенаправляется на вход', function (): void {
    get(route('dashboard'))->assertRedirect(route('login'));
    getJson(route('dashboard'))->assertUnauthorized();
});

test('пользователь видит кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Dashboard'));
});

test('неподтверждённый email закрывает кабинет и рабочие списки', function (): void {
    actingAs(User::factory()
        ->unverified()
        ->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
    getJson(route('dashboard'))->assertForbidden();
});
