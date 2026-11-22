<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\post;

test('разделяет совпадающие внешние ID и повторно подтверждает без дублей', function (): void {
    $owner = User::factory()->create();
    actingAs($owner);

    foreach (['первый', 'второй'] as $title) {
        $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
        $preview = ClippingImport::query()->create([
            'clipping_connection_id' => $connection->id, 'external_id' => 'game', 'source_name' => $title, 'clip_count' => 1,
            'clips' => [['id' => 'clip', 'title' => $title, 'durationMs' => 3000, 'score' => null, 'publications' => []]],
        ]);

        $result = post(route('clipping.import', [$connection, $preview->id]));
        $project = Project::query()->where('clipping_connection_id', $connection->id)->sole();
        $result->assertRedirect(route('projects.show', $project));
        post(route('clipping.import', [$connection, $preview->id]))->assertRedirect(route('projects.show', $project));

        expect($project->owner_id)->toBe($owner->id)
            ->and($project->isClippingProject())->toBeTrue()
            ->and($preview->refresh()->imported_at)->not->toBeNull();
    }

    assertDatabaseCount('projects', 2);
    assertDatabaseCount('opus_sources', 2);
    assertDatabaseCount('opus_clips', 2);
    assertDatabaseCount('posts', 0);
    assertDatabaseCount('publication_requests', 0);
    Http::assertNothingSent();
});

test('две игры используют один импортированный канал без права публикации', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $account = ['id' => 'provider-profile', 'platform' => 'youtube', 'external_id' => 'external-channel', 'name' => 'Студия', 'channel_id' => null];
    actingAs($owner);

    foreach (['race', 'forest'] as $source) {
        $preview = ClippingImport::query()->create([
            'clipping_connection_id' => $connection->id, 'external_id' => $source,
            'clips' => [], 'clip_count' => 0, 'accounts' => [$account],
        ]);
        post(route('clipping.import', [$connection, $preview->id]))->assertRedirect();
    }

    $channel = Channel::query()->sole();
    expect($channel->is_active)->toBeFalse()
        ->and($channel->credentials)->toBeNull()
        ->and($channel->owner_id)->toBe($owner->id)
        ->and($channel->projects()->count())->toBe(2);
    assertDatabaseCount('clipping_account_mappings', 1);
    assertDatabaseCount('publication_requests', 0);
});

test('повторно проверяет владельца канала перед подтверждением', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $otherChannel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $preview = ClippingImport::query()->create([
        'clipping_connection_id' => $connection->id, 'external_id' => 'game', 'clips' => [], 'clip_count' => 0,
        'accounts' => [['id' => 'a', 'platform' => 'youtube', 'external_id' => 'b', 'name' => 'Автор', 'channel_id' => $otherChannel->id]],
    ]);
    actingAs($owner);

    post(route('clipping.import', [$connection, $preview->id]))->assertNotFound();

    expect($preview->refresh()->imported_at)->toBeNull();
    assertDatabaseCount('opus_sources', 0);
    assertDatabaseCount('clipping_account_mappings', 0);
});

test('сохраняет привязку проекта при подтверждении устаревшего выбора подключения', function (): void {
    $owner = User::factory()->create();
    $first = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $second = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $project = Project::factory()->create(['owner_id' => $owner->id, 'clipping_connection_id' => $first->id]);
    $preview = ClippingImport::query()->create([
        'clipping_connection_id' => $second->id, 'project_id' => $project->id,
        'external_id' => 'other-source', 'clip_count' => 0, 'clips' => [],
    ]);
    actingAs($owner);

    post(route('clipping.import', [$second, $preview->id]))->assertSessionHasErrors('project_id');

    expect($project->refresh()->clipping_connection_id)->toBe($first->id)
        ->and($preview->refresh()->imported_at)->toBeNull();
    assertDatabaseCount('opus_sources', 0);
});
