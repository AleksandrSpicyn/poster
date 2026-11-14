<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;

test('форма входа доступна гостю', function (): void {
    get(route('login'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('auth/Login'));
});

test('авторизованный пользователь перенаправляется в кабинет', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});
