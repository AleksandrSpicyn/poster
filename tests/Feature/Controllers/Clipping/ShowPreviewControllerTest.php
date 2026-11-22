<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\User;

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
