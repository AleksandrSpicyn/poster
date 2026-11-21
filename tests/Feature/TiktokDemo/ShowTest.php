<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

test('демо выключено по умолчанию и требует отдельного входа', function (): void {
    get(route('tiktok-demo.show'))->assertNotFound();
    config(['tiktok_demo.enabled' => true]);

    get(route('tiktok-demo.show'))->assertUnauthorized();
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
