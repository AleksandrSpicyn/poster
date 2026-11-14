<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('гость не читает журналы приложения', function (): void {
    get(route('log-viewer.index'))->assertRedirect(route('login'));
    getJson(route('log-viewer.index'))->assertUnauthorized();
});

test('владелец открывает журналы', function (): void {
    $user = User::factory()->create();

    config(['services.admin.email' => $user->email]);

    actingAs($user)
        ->get(route('log-viewer.index'))
        ->assertOk();
});

test('другой пользователь не читает журналы', function (): void {
    config(['services.admin.email' => 'owner@example.com']);

    actingAs(User::factory()->create())
        ->get(route('log-viewer.index'))
        ->assertForbidden();
});

test('неподтверждённый владелец сначала проверяет email', function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    config(['services.admin.email' => $user->email]);

    actingAs($user)
        ->get(route('log-viewer.index'))
        ->assertRedirect(route('verification.notice'));
});
