<?php

use App\Models\User;

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
    $response->assertOk()->assertInertia(fn(\Inertia\Testing\AssertableInertia $page) => $page->component('Dashboard'));
});
