<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

test('меняет ключ только у выбранного подключения и сохраняет его связи', function (): void {
    Http::fake(['*/clip-projects*' => Http::response([])]);
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'uses_server_key' => true]);
    $connection->forceFill(['access_result' => ['projects' => 'available'], 'access_checked_at' => now()])->save();
    $version = $connection->scope_version;
    $other = ClippingConnection::factory()->create(['owner_id' => $owner->id, 'api_key' => 'first-key']);
    $project = Project::factory()->create(['owner_id' => $owner->id, 'clipping_connection_id' => $connection->id]);
    actingAs($owner);

    patch(route('clipping.key.update', $connection), ['api_key' => 'new-key'])
        ->assertRedirect(route('clipping.edit', $connection));

    expect($connection->refresh()->api_key)->toBe('new-key')
        ->and($connection->getRawOriginal('api_key'))->not->toBe('new-key')
        ->and($connection->uses_server_key)->toBeFalse()
        ->and($connection->scope_version)->toBe($version + 1)
        ->and($connection->access_result)->toBeNull()
        ->and($connection->access_checked_at)->toBeNull()
        ->and($other->refresh()->api_key)->toBe('first-key')
        ->and($project->refresh()->clipping_connection_id)->toBe($connection->id);
    Http::assertSentCount(1);
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

test('ошибка замены ключа не возвращает введённый секрет в сессию', function (): void {
    $connection = ClippingConnection::factory()->create(['api_key' => 'original-key']);
    $owner = User::query()->findOrFail($connection->owner_id);
    $invalid = str_repeat('x', 4097);

    actingAs($owner)
        ->from(route('clipping.edit', $connection))
        ->patch(route('clipping.key.update', $connection), ['api_key' => $invalid])
        ->assertRedirect(route('clipping.edit', $connection))
        ->assertSessionHasErrors(['api_key' => 'Ключ API не должен быть длиннее 4096 символов.'])
        ->assertSessionMissing('_old_input.api_key');

    expect($connection->refresh()->api_key)->toBe('original-key');
    Http::assertNothingSent();
});
