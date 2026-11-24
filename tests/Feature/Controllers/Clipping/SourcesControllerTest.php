<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\OpusClip;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ClipsOnlyReader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('читает источники и идентификаторы аккаунтов', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'api_key' => 'source-key']);
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => [['projectId' => 'game', 'title' => 'Игра']]]),
        '*/social-accounts*' => Http::response(['data' => [['postAccountId' => 'account', 'platform' => 'YOUTUBE', 'extUserId' => 'channel', 'extUserName' => 'Автор']]]),
    ]);

    actingAs($owner);
    get(route('clipping.sources', $connection))
        ->assertInertia(fn(Assert $page): Assert => $page->component('Clipping/Sources')->where('sources.0.id', 'game')->where('accounts.0.external_id', 'channel')->missing('connection.api_key'));

    foreach (Http::recorded() as [$request]) {
        expect($request->hasHeader('Authorization', 'Bearer source-key'))->toBeTrue();
    }
});

test('закрывает чтение чужого подключения до обращения к провайдеру', function (): void {
    $connection = ClippingConnection::factory()->create();
    actingAs(User::factory()->create());

    get(route('clipping.sources', $connection))->assertNotFound();

    Http::assertNothingSent();
});

test('драйвер без аккаунтов предоставляет проекты и импортирует клипы', function (): void {
    config(['clipping.drivers.fixture' => ['label' => 'Тестовый драйвер', 'reader' => ClipsOnlyReader::class]]);
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'driver' => 'fixture']);
    actingAs($owner);

    get(route('clipping.sources', $connection))
        ->assertInertia(fn(Assert $page): Assert => $page->where('readsAccounts', false)->has('accounts', 0)->where('sources.0.id', 'sample'));
    post(route('clipping.preview', $connection), ['external_id' => 'sample'])->assertRedirect();
    $preview = ClippingImport::query()->sole();
    post(route('clipping.import', [$connection, $preview->id]))->assertRedirect();

    expect(OpusClip::query()->sole()->title)->toBe('Тестовый клип');
    Http::assertNothingSent();
});

test('не открывает настройки сопоставления по предпросмотру другого подключения', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $foreign = ClippingConnection::factory()->create();
    $preview = ClippingImport::query()->create(['clipping_connection_id' => $foreign->id, 'clips' => [], 'clip_count' => 0]);
    actingAs($owner);

    get(route('clipping.sources', ['connection' => $connection, 'preview' => $preview->id]))->assertNotFound();

    Http::assertNothingSent();
});

test('фильтрует внешние проекты по имени в выбранном подключении', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'api_key' => 'local-reader', 'import_accounts' => false]);
    Http::fake(['*/clip-projects*' => Http::response(['list' => [
        ['projectId' => 'night', 'title' => 'Ночной маршрут'],
        ['projectId' => 'river', 'title' => 'Река'],
    ]])]);

    actingAs($owner)->get(route('clipping.sources', ['connection' => $connection, 'search' => 'НОЧ']))
        ->assertInertia(fn(Assert $page): Assert => $page->where('search', 'НОЧ')->has('sources', 1)->where('sources.0.id', 'night'));

    get(route('clipping.sources', ['connection' => $connection, 'search' => 'Луна']))
        ->assertInertia(fn(Assert $page): Assert => $page->has('sources', 0));

    Http::assertSentCount(2);
});

test('обновление списка проектов не запрашивает аккаунты площадки', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => []]),
        '*/social-accounts*' => Http::response(['data' => []]),
    ]);

    actingAs($owner)
        ->get(route('clipping.sources', $connection))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->reloadOnly('sources', fn(Assert $partial): Assert => $partial
                ->has('sources', 0)
                ->missing('accounts')));

    Http::assertSentCount(3);
});
