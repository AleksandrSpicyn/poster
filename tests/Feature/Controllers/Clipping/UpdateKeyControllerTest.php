<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

test('меняет ключ только у выбранного подключения и сохраняет его связи', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'uses_server_key' => true]);
    $other = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'api_key' => 'first-key']);
    $project = Project::factory()->create(['owner_id' => $owner->id, 'clipping_connection_id' => $connection->id]);
    actingAs($owner);

    patch(route('clipping.key.update', $connection), ['api_key' => 'new-key'])
        ->assertRedirect(route('clipping.edit', $connection));

    expect($connection->refresh()->api_key)->toBe('new-key')
        ->and($connection->getRawOriginal('api_key'))->not->toBe('new-key')
        ->and($connection->uses_server_key)->toBeFalse()
        ->and($other->refresh()->api_key)->toBe('first-key')
        ->and($project->refresh()->clipping_connection_id)->toBe($connection->id);
    Http::assertNothingSent();
});

test('отклоняет пустой ключ и попытку изменить чужое подключение', function (): void {
    $connection = ClippingConnection::factory()->create(['api_key' => 'old-key']);
    actingAs(User::query()->findOrFail($connection->owner_id));

    patch(route('clipping.key.update', $connection), ['api_key' => ''])->assertSessionHasErrors('api_key');
    actingAs(User::factory()->create());
    patch(route('clipping.key.update', $connection), ['api_key' => 'replace-key'])->assertNotFound();

    expect($connection->refresh()->api_key)->toBe('old-key');
    Http::assertNothingSent();
});
