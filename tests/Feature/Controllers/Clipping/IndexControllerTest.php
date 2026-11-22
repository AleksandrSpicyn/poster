<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('видны только свои подключения без ключей', function (): void {
    $owner = User::factory()->create();
    $mine = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    ClippingConnection::factory()->create();

    actingAs($owner);
    get(route('clipping.index'))
        ->assertInertia(fn(Assert $page): Assert => $page->component('Clipping/Index')->has('connections', 1)->where('connections.0.id', $mine->id)->missing('connections.0.api_key'));
});
