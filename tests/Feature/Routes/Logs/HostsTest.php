<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

test('API журналов требует входа', function (): void {
    getJson(route('log-viewer.hosts'))->assertUnauthorized();
});

test('владелец получает список источников журналов', function (): void {
    $user = User::factory()->create();

    config(['services.admin.email' => $user->email]);

    actingAs($user)
        ->getJson(route('log-viewer.hosts'))
        ->assertOk()
        ->assertJsonPath('data.0.identifier', 'local');
});

test('API журналов недоступен постороннему', function (): void {
    config(['services.admin.email' => 'owner@example.com']);

    actingAs(User::factory()->create())
        ->getJson(route('log-viewer.hosts'))
        ->assertForbidden();
});

test('API журналов недоступен неподтверждённому владельцу', function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    config(['services.admin.email' => $user->email]);

    actingAs($user)
        ->getJson(route('log-viewer.hosts'))
        ->assertForbidden();
});
