<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\ClippingAccountMapping;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('сверяет принадлежность предпросмотра подключению', function (): void {
    $owner = User::factory()->create();
    $mine = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $other = ClippingConnection::factory()->create();
    $preview = ClippingImport::query()->create(['clipping_connection_id' => $other->id, 'external_id' => 'game', 'clips' => [], 'clip_count' => 0]);
    actingAs($owner);

    get(route('clipping.preview.show', [$mine, $preview->id]))->assertNotFound();
});

test('показывает проект и имена доступных каналов предпросмотра', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $project = Project::factory()->for($owner, 'owner')->create(['content_mode' => 'short']);
    $channel = Channel::factory()->for($project)->create(['type' => 'youtube']);
    $foreign = Channel::factory()->create();
    ClippingAccountMapping::query()->create([
        'clipping_connection_id' => $connection->id, 'provider_account_id' => 'mapped',
        'platform' => 'youtube', 'external_id' => 'studio', 'channel_id' => $channel->id,
    ]);
    $account = ['platform' => 'youtube', 'external_id' => 'studio', 'name' => 'Studio'];
    $preview = ClippingImport::query()->create([
        'clipping_connection_id' => $connection->id, 'project_id' => $project->id,
        'external_id' => 'source', 'clips' => [], 'clip_count' => 0,
        'accounts' => [
            [...$account, 'id' => 'mapped', 'channel_id' => null],
            [...$account, 'id' => 'foreign', 'channel_id' => $foreign->id],
        ],
    ]);
    actingAs($owner);

    get(route('clipping.preview.show', [$connection, $preview->id]))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('projectName', $project->name)
            ->where('accountNames.mapped', $channel->name)
            ->missing('accountNames.foreign'));
});
