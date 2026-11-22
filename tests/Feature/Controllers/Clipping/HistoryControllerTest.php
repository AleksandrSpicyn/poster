<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('история ограничена подключениями владельца', function (): void {
    $mine = User::factory()->create();

    foreach ([$mine, User::factory()->create()] as $user) {
        $connection = ClippingConnection::factory()->create(['owner_id' => $user->id]);
        ClippingImport::query()->create(['clipping_connection_id' => $connection->id, 'external_id' => 'source-' . $user->id, 'clips' => [], 'clip_count' => 0]);
    }

    actingAs($mine);
    get(route('clipping.history'))
        ->assertInertia(fn(Assert $page): Assert => $page->component('Clipping/History')->has('imports', 1)->where('imports.0.external_id', 'source-' . $mine->id));
});
