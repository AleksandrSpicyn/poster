<?php

declare(strict_types=1);

use App\Jobs\RefreshClippingSource;
use App\Models\ClippingConnection;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

test('владелец возобновляет источник с сохранённого этапа один раз', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $project = Project::factory()->create(['owner_id' => $owner->id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'sync_paused_at' => now(), 'publication_sync_checkpoint' => fake()->uuid()]);
    $checkpoint = $source->publication_sync_checkpoint;
    $url = route('clipping.sources.resume', [$connection, $source]);
    actingAs($owner);

    post($url)->assertRedirect(route('clipping.history'));
    post($url)->assertRedirect(route('clipping.history'));

    Queue::assertPushed(RefreshClippingSource::class, 1);
    expect($source->refresh()->sync_paused_at)->toBeNull()
        ->and($source->publication_sync_checkpoint)->toBe($checkpoint);
});

test('чужой источник и источник другого подключения остаются на паузе', function (): void {
    Queue::fake();
    $connection = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $connection->owner_id, 'clipping_connection_id' => $connection->id]);
    $source = OpusSource::factory()->for($project)->create(['clipping_connection_id' => $connection->id, 'sync_paused_at' => now()]);
    $other = User::factory()->create();
    $own = ClippingConnection::factory()->create(['owner_id' => $other->id]);
    actingAs($other);

    post(route('clipping.sources.resume', [$connection, $source]))->assertNotFound();
    post(route('clipping.sources.resume', [$own, $source]))->assertNotFound();

    Queue::assertNothingPushed();
    expect($source->refresh()->sync_paused_at)->not->toBeNull();
});
