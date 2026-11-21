<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

test('обменивает одноразовый код и привязывает подтверждённый профиль владельцу', function (): void {
    authenticateDemoAuthor();
    Http::fake(['*/oauth/token/' => Http::response(['access_token' => 'fake-access', 'refresh_token' => 'fake-refresh', 'expires_in' => 3600, 'open_id' => 'fake-user', 'scope' => 'user.info.basic,video.list,video.upload,video.publish']),
        '*/user/info/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['user' => ['open_id' => 'fake-user', 'display_name' => 'Alpha']]])]);
    post(route('tiktok-demo.connect', 'author-a'));
    $callback = route('tiktok-demo.callback', ['code' => 'test-code', 'state' => session('tiktok_demo.state.value')]);

    get($callback)->assertRedirect(route('tiktok-demo.show', 'author-a'))->assertSessionHas('tiktok_demo.author-a.profile.name', 'Alpha')
        ->assertSessionMissing('tiktok_demo.author-b.profile')->assertSessionMissing('tiktok_demo.state');
    get($callback)->assertForbidden();

    Http::assertSentCount(2);
    expect(session('tiktok_demo.author-a.profile.access'))->not->toBe('fake-access');
});

test('отклоняет чужой, просроченный или подменённый state до обращения к API', function (string $actor, int $minutes, string $state): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.state' => ['author' => $actor, 'value' => 'original', 'expires' => now()->addMinutes($minutes)->timestamp]]);

    get(route('tiktok-demo.callback', ['state' => $state, 'code' => 'test']))->assertForbidden();

    Http::assertNothingSent();
})->with([['author-b', 10, 'original'], ['author-a', -1, 'original'], ['author-a', 10, 'modified']]);

test('частичный набор разрешений не создаёт подключения', function (): void {
    authenticateDemoAuthor();
    Http::fake(['*/oauth/token/' => Http::response(['access_token' => 'fake', 'refresh_token' => 'fake', 'expires_in' => 3600, 'open_id' => 'fake', 'scope' => 'user.info.basic'])]);
    post(route('tiktok-demo.connect', 'author-a'));

    get(route('tiktok-demo.callback', ['code' => 'test', 'state' => session('tiktok_demo.state.value')]))->assertRedirect()
        ->assertSessionHas('tiktok_demo.author-a.notice')->assertSessionMissing('tiktok_demo.author-a.profile');

    Http::assertSentCount(1);
});

test('отказ владельца завершает вход без обмена кода', function (): void {
    authenticateDemoAuthor();
    post(route('tiktok-demo.connect', 'author-a'));

    get(route('tiktok-demo.callback', ['error' => 'access_denied', 'state' => session('tiktok_demo.state.value')]))
        ->assertRedirect(route('tiktok-demo.show', 'author-a'))
        ->assertSessionHas('tiktok_demo.author-a.notice', 'Подключение отменено.')
        ->assertSessionMissing('tiktok_demo.author-a.profile');

    Http::assertNothingSent();
});
