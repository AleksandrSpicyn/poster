<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('настройки показывают только свои подключения и связанные проекты', function (): void {
    $owner = User::factory()->create();
    $one = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $other = ClippingConnection::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id, 'clipping_connection_id' => $one->id]);
    Project::factory()->create(['owner_id' => $owner->id]);
    actingAs($owner);

    get(route('clipping.edit', $one))->assertInertia(fn(Assert $page): Assert => $page->component('Clipping/Edit')
        ->has('connections', 2)->has('projects', 1)->where('projects.0.id', $project->id)
        ->where('scope.import_projects', true)->where('supportsArchives', false)->missing('connection.api_key')->missing('access'));
    get(route('clipping.edit', $other))->assertNotFound();

    Http::assertNothingSent();
});
