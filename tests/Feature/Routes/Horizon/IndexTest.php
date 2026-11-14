<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

test('гость перенаправляется на вход', function (): void {
    get(route('horizon.index'))->assertRedirect(route('login'));
    getJson(route('horizon.index'))->assertUnauthorized();
});

test('разрешённый оператор видит dashboard очередей', function (): void {
    $user = User::factory()->create();

    config(['horizon.allowed_emails' => [$user->email]]);

    actingAs($user)
        ->get(route('horizon.index'))
        ->assertOk()
        ->assertSee('Horizon');
});

test('другой пользователь не получает доступ к очередям', function (): void {
    config(['horizon.allowed_emails' => ['operator@example.com']]);

    actingAs(User::factory()->create())
        ->get(route('horizon.index'))
        ->assertForbidden();
});

test('неподтверждённый оператор сначала проверяет email', function (): void {
    $user = User::factory()
        ->unverified()
        ->create();

    config(['horizon.allowed_emails' => [$user->email]]);

    actingAs($user)
        ->get(route('horizon.index'))
        ->assertRedirect(route('verification.notice'));

    getJson(route('horizon.index'))->assertForbidden();
});
