<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withUnencryptedCookie;

test('владелец видит настройки оформления', function (): void {
    actingAs(User::factory()->create())
        ->get(route('appearance'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('settings/Appearance'));
});

test('гость не получает доступ к маршруту', function (): void {
    $url = route('appearance');

    get($url)->assertRedirect(route('login'));
    getJson($url)->assertUnauthorized();
});

test('страница получает значения оформления из enum', function (): void {
    actingAs(User::factory()->create())
        ->get(route('appearance'))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->where('appearanceValues', [
            'LIGHT' => 'light', 'DARK' => 'dark', 'SYSTEM' => 'system',
        ]));
});

test('оформление cookie применяется к HTML', function (string $cookie, string $theme): void {
    withUnencryptedCookie('appearance', $cookie);

    get(route('home'))
        ->assertOk()
        ->assertSee('const appearance = "' . $theme . '";', escape: false)
        ->assertInertia(fn(Assert $page): Assert => $page->where('appearance', $theme));
})->with([
    'светлое' => ['light', 'light'],
    'тёмное' => ['dark', 'dark'],
    'системное' => ['system', 'system'],
    'неизвестное' => ['invalid', 'system'],
]);
