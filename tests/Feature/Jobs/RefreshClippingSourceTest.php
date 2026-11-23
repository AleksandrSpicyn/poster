<?php

declare(strict_types=1);

use App\Jobs\RefreshClippingSource;
use App\Models\ClippingConnection;
use App\Models\ClippingSyncRun;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;

test('планировщик обновляет привязанный источник с ключом его владельца', function (): void {
    $connection = ClippingConnection::factory()->create(['api_key' => 'sync-fixture']);
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'game']);
    Http::fake(['*/clip-projects*' => Http::response([]), '*/exportable-clips*' => Http::response([['id' => 'clip']]), '*/post-tasks*' => Http::response(['data' => ['data' => []]])]);

    consoleCommand('clipping:refresh')->assertSuccessful();

    expect($source->refresh()->last_synced_at)->not->toBeNull();
    Http::assertSentCount(3);

    foreach (Http::recorded() as [$request]) {
        expect($request->hasHeader('Authorization', 'Bearer sync-fixture'))->toBeTrue();
    }
});

test('прекращает обновление после смены подключения проекта', function (): void {
    $connection = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'game']);
    $project->clipping_connection_id = null;
    $project->save();

    RefreshClippingSource::dispatchSync($connection->id, $source->id, 'game');

    Http::assertNothingSent();
    expect($source->refresh()->last_synced_at)->toBeNull();
});

test('пауза при отзыве доступа сохраняет историю источника', function (): void {
    $connection = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'game']);
    Http::fake(['*/clip-projects*' => Http::response([]), '*/exportable-clips*' => Http::response([], 403)]);

    consoleCommand('clipping:refresh')->assertSuccessful();
    consoleCommand('clipping:refresh')->assertSuccessful();

    Http::assertSentCount(3);
    expect($source->refresh()->sync_paused_at)->not->toBeNull()
        ->and($source->sync_error)->toBe('HTTP 403');
});

test('после лимита продолжает с публикаций и сохраняет принятые клипы', function (): void {
    $connection = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'game']);
    $existing = OpusClip::factory()->for($source, 'source')->create(['clip_id' => 'clip', 'publications' => [['platform' => 'youtube', 'url' => null, 'status' => 'PROCESSING', 'publish_at' => null]]]);
    Http::fake([
        '*/clip-projects*' => Http::response([]),
        '*/exportable-clips*' => Http::response([['id' => 'game.clip', 'title' => 'Ночная поездка']]),
        '*/post-tasks*' => Http::sequence()->push([], 429, ['Retry-After' => '60'])
            ->push(['data' => ['data' => [['projectId' => 'game', 'clipId' => 'game.clip', 'platform' => 'YOUTUBE', 'status' => 'PUBLISHED', 'extVideoLink' => 'https://youtu.be/abcdefghijk']]]]),
    ]);

    consoleCommand('clipping:refresh')->assertSuccessful();

    expect($source->refresh()->publication_sync_checkpoint)->not->toBeNull()
        ->and($source->last_synced_at)->toBeNull()
        ->and($existing->refresh()->title)->toBe('Ночная поездка')
        ->and($existing->publications[0]['status'])->toBe('PROCESSING');

    $partial = ClippingSyncRun::query()->sole();

    expect($partial->clips_received)->toBe(1)
        ->and($partial->clips_added)->toBe(0)
        ->and($partial->publications_added)->toBe(0)
        ->and($partial->failed_at)->not->toBeNull()
        ->and($partial->completed_at)->toBeNull();

    RefreshClippingSource::dispatchSync($connection->id, $source->id, 'game');

    expect($partial->refresh()->resumed_at)->not->toBeNull()
        ->and($partial->completed_at)->not->toBeNull()
        ->and($partial->clips_received)->toBe(1)
        ->and($partial->publications_added)->toBe(1)
        ->and(ClippingSyncRun::query()->count())->toBe(1);
    Http::assertSentCount(4);
    expect($source->refresh()->publication_sync_checkpoint)->toBeNull()
        ->and($source->last_synced_at)->not->toBeNull()
        ->and(OpusClip::query()->where('opus_source_id', $source->id)->count())->toBe(1)
        ->and($existing->refresh()->publications[0]['url'])->toBe('https://youtu.be/abcdefghijk');
});

test('отключение проектов после постановки в очередь сохраняет источник без чтения клипов', function (): void {
    $connection = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'race']);
    $job = new RefreshClippingSource($connection->id, $source->id, 'race');
    $connection->forceFill(['import_projects' => false])->save();

    app()->call($job->handle(...));

    Http::assertNothingSent();
    expect($source->refresh()->last_synced_at)->toBeNull();
});

test('отказ старого ключа не останавливает обновлённое подключение', function (): void {
    $connection = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'game']);
    Http::fake(function () use ($connection) {
        $connection->increment('scope_version');

        return Http::response([], 403);
    });

    RefreshClippingSource::dispatchSync($connection->id, $source->id, 'game');

    expect($source->refresh()->sync_paused_at)->toBeNull()
        ->and($source->sync_error)->toBeNull()
        ->and(ClippingSyncRun::query()->count())->toBe(0);
});
