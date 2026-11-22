<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;

test('зарегистрированный адрес TikTok передаёт ответ штатному callback', function (array $query): void {
    get(route('channels.tiktok-redirect', [...$query, 'provider' => 'youtube', 'next' => '/']))
        ->assertRedirect(route('channels.provider-callback', ['provider' => 'tiktok', ...$query]))
        ->assertHeader('Referrer-Policy', 'no-referrer');

    Http::assertNothingSent();
})->with([
    'согласие' => [['state' => 'test-state', 'code' => 'a+b/c=']],
    'отмена' => [['state' => 'test-state', 'error' => 'access_denied']],
]);
