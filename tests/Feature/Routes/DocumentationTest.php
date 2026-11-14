<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Tests\Support\consoleCommand;

test('владелец видит сгенерированную документацию', function (): void {
    consoleCommand('scribe:generate')->assertSuccessful();

    actingAs(User::factory()->create())
        ->get(route('scribe'))
        ->assertOk()
        ->assertSee('Список публикаций');
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('scribe');

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});
