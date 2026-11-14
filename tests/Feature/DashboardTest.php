<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('гость перенаправляется на вход', function (): void {
    $response = get('/dashboard');
    $response->assertRedirect('/login');
});

test('пользователь видит кабинет', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $response = get('/dashboard');
    $response->assertOk()->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('Dashboard'));
});
