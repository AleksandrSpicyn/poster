<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ClipsOnlyReader;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('разделяет права проектов клипов и аккаунтов по ответам провайдера', function (): void {
    $connection = ClippingConnection::factory()->create(['api_key' => 'private-key']);
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => [['projectId' => 'racing', 'title' => 'Гонки']]]),
        '*/exportable-clips*' => Http::response([], 403),
        '*/post-tasks*' => Http::response([], 403),
        '*/social-accounts*' => Http::response(['data' => []]),
    ]);
    actingAs(User::query()->findOrFail($connection->owner_id));

    $response = get(route('clipping.access', $connection));
    $response->assertInertia(fn(Assert $page): Assert => $page->where('access.projects', 'available')->where('access.accounts', 'available')
        ->where('access.clips', 'denied')->where('access.publications', 'denied')->where('access.account_count', 0)->where('access.metrics', 'unsupported')->where('access.sample', 'Гонки')->where('access.titles', ['Гонки']));
    $response->assertDontSee('private-key');

    Http::assertSentCount(4);
});

test('пустой список проектов и временный сбой аккаунтов не подтверждают чтение клипов', function (): void {
    $connection = ClippingConnection::factory()->create();
    Http::fake(['*/clip-projects*' => Http::response(['list' => []]), '*/social-accounts*' => Http::response([], 503)]);
    actingAs(User::query()->findOrFail($connection->owner_id));

    get(route('clipping.access', $connection))->assertInertia(fn(Assert $page): Assert => $page->where('access.projects', 'available')
        ->where('access.clips', 'unchecked')->where('access.accounts', 'unavailable')->where('access.titles', []));

    Http::assertSentCount(2);
});

test('учитывает возможности выбранного драйвера и закрывает чужой ключ', function (): void {
    config(['clipping.drivers.fixture' => ['label' => 'Драйвер', 'reader' => ClipsOnlyReader::class]]);
    $connection = ClippingConnection::factory()->create(['driver' => 'fixture']);
    actingAs(User::query()->findOrFail($connection->owner_id));

    get(route('clipping.access', $connection))->assertInertia(fn(Assert $page): Assert => $page->where('access.accounts', 'unsupported')->where('access.clips', 'available'));
    actingAs(User::factory()->create());
    get(route('clipping.access', $connection))->assertNotFound();

    Http::assertNothingSent();
});

test('проверяет связанный источник выбранного проекта и не обращается к чужому', function (): void {
    $connection = ClippingConnection::factory()->create();
    $owner = User::query()->findOrFail($connection->owner_id);
    $project = Project::factory()->create(['owner_id' => $owner->id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'second']);
    $foreign = Project::factory()->create();
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => [['projectId' => 'first'], ['projectId' => 'second', 'title' => 'Второй']]]),
        '*/exportable-clips*' => Http::response([]),
        '*/post-tasks*' => Http::response(['data' => ['data' => []]]),
        '*/social-accounts*' => Http::response([]),
    ]);
    actingAs($owner);

    get(route('clipping.access', ['connection' => $connection, 'project_id' => $foreign->id]))->assertNotFound();
    Http::assertNothingSent();
    get(route('clipping.access', ['connection' => $connection, 'source_id' => $source->id]))
        ->assertInertia(fn(Assert $page): Assert => $page->where('access.source', 'second')->where('access.project_id', $project->id)->where('access.clips', 'available'));

    expect($connection->refresh()->getAttribute('access_result'))->toMatchArray(['source' => 'second', 'projects' => 'available']);
    expect($connection->getAttribute('access_checked_at'))->not->toBeNull();
    Http::assertSentCount(4);
});
