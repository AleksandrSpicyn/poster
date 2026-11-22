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
    $mine->forceFill(['access_result' => ['projects' => 'available', 'clips' => 'denied', 'sample' => 'internal-sample']])->save();
    ClippingConnection::factory()->create();

    actingAs($owner);
    get(route('clipping.index'))
        ->assertInertia(fn(Assert $page): Assert => $page->component('Clipping/Index')->has('connections', 1)->where('connections.0.id', $mine->id)->where('connections.0.access.projects', 'available')->where('connections.0.access.clips', 'denied')->missing('connections.0.access.sample')->missing('connections.0.api_key'));
});
