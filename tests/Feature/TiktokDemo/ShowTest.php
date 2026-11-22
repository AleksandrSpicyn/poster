<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

test('демо выключено по умолчанию и открывается без пароля после включения', function (): void {
    app()->instance('env', 'production');

    get(route('tiktok-demo.show'))->assertNotFound();
    config(['tiktok_demo.enabled' => true]);

    get(route('tiktok-demo.show'))->assertOk()->assertSee('Подключить TikTok')->assertSessionHas('tiktok_demo.owner');
});

test('автор видит только свой профиль, счётчики и операции', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha'), 'tiktok_demo.author-b.profile' => demoProfile('Bravo'),
        'tiktok_demo.author-b.operation' => 'private-operation']);
    Http::fake(['*/video/list/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['videos' => [['id' => 'fake', 'title' => 'Первый клип', 'view_count' => 42, 'like_count' => 0]], 'has_more' => true, 'cursor' => 77]])]);

    get(route('tiktok-demo.show', 'author-b'))->assertNotFound();
    Http::assertNothingSent();
    get(route('tiktok-demo.show', ['author' => 'author-a', 'cursor' => 88]))->assertOk()->assertSee('Alpha')->assertSee('Первый клип')->assertSee('42')->assertSee('—')->assertSee('cursor=77')->assertDontSee('Bravo')->assertDontSee('private-operation')->assertDontSee('test-access');

    Http::assertSent(fn(Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-access-Alpha') && $request['cursor'] === 88);
});

test('различает пустой список и отказ провайдера', function (int $status, string $shown, string $hidden): void {
    authenticateDemoAuthor('author-b');
    withSession(['tiktok_demo.author-b.profile' => demoProfile('Bravo')]);
    Http::fake(['*' => Http::response(['data' => ['videos' => []], 'error' => ['code' => 'ok']], $status)]);

    get(route('tiktok-demo.show', 'author-b'))->assertOk()->assertSee($shown)->assertDontSee($hidden);
})->with([[200, 'Публичных роликов пока нет', 'Не удалось загрузить'], [500, 'Не удалось загрузить', 'Публичных роликов пока нет']]);

test('смена окружения сбрасывает профиль, ожидающий callback и операцию', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha'), 'tiktok_demo.state' => ['value' => 'old'],
        'tiktok_demo.author-a.direct.id' => 'old-operation']);
    config(['tiktok_demo.mode' => 'production']);

    get(route('tiktok-demo.show'))->assertOk()->assertSee('Подключить TikTok')->assertDontSee('Alpha')
        ->assertSessionMissing('tiktok_demo.state')->assertSessionMissing('tiktok_demo.author-a.direct.id');

    Http::assertNothingSent();
});

test('снимает ожидание после подтверждения публикации в TikTok', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha'), 'tiktok_demo.author-a.direct.pending' => true, 'tiktok_demo.author-a.direct.id' => 'test-post']);
    Http::fake(['*/creator_info/query/' => Http::response(demoCreatorResponse()), '*/status/fetch/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['status' => 'PUBLISH_COMPLETE']]),
        '*/video/list/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['videos' => []]])]);

    get(route('tiktok-demo.show', 'author-a'))->assertOk()->assertSee('TikTok подтвердил публикацию')->assertDontSee('value="PUBLIC_TO_EVERYONE"', false)
        ->assertSessionMissing('tiktok_demo.author-a.direct.pending');

    get(route('tiktok-demo.show', 'author-a'))
        ->assertSeeInOrder(['Статус публикации в TikTok', 'TikTok подтвердил публикацию.', 'Опубликовать ещё один ролик'])
        ->assertDontSee('Обновить статус');
});

test('поясняет старую квитанцию без идентификатора при обновлении', function (): void {
    authenticateDemoAuthor();
    withSession(['tiktok_demo.author-a.profile' => demoProfile('Alpha'), 'tiktok_demo.author-a.direct.pending' => true,
        'tiktok_demo.author-a.direct.message' => 'Передача не подтверждена. Проверьте результат перед повторной отправкой.']);
    Http::fake(['*/video/list/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['videos' => []]])]);

    get(route('tiktok-demo.show', 'author-a'))->assertOk()->assertSee('Идентификатор TikTok не сохранён')->assertSessionHas('tiktok_demo.author-a.direct.pending', true);

    Http::assertSentCount(1);
});
