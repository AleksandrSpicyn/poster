<?php

declare(strict_types=1);

use App\Jobs\RefreshClippingSource;
use App\Models\ClippingConnection;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;

test('планировщик обновляет привязанный источник с ключом его владельца', function (): void {
    $connection = ClippingConnection::factory()->create(['api_key' => 'sync-fixture']);
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'external_id' => 'game']);
    Http::fake(['*/exportable-clips*' => Http::response([['id' => 'clip']]), '*/post-tasks*' => Http::response(['data' => ['data' => []]])]);

    consoleCommand('clipping:refresh')->assertSuccessful();

    expect($source->refresh()->last_synced_at)->not->toBeNull();
    Http::assertSentCount(2);

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
    Http::fake(['*/exportable-clips*' => Http::response([], 403)]);

    consoleCommand('clipping:refresh')->assertSuccessful();
    consoleCommand('clipping:refresh')->assertSuccessful();

    Http::assertSentCount(1);
    expect($source->refresh()->sync_paused_at)->not->toBeNull()
        ->and($source->sync_error)->toBe('HTTP 403');
});
