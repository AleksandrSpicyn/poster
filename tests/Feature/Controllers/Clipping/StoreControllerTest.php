<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

test('сохраняет зашифрованный ключ владельца', function (): void {
    $owner = User::factory()->create();
    actingAs($owner);

    post(route('clipping.store'), ['name' => 'Студия', 'driver' => 'opusclip', 'api_key' => 'fixture-only'])
        ->assertRedirect(route('clipping.index'));

    $connection = ClippingConnection::query()->sole();
    expect($connection->api_key)->toBe('fixture-only')
        ->and($connection->owner_id)->toBe($owner->id)
        ->and(DB::table('clipping_connections')->value('api_key'))->not->toContain('fixture-only');
});

test('отклоняет неизвестный драйвер', function (): void {
    actingAs(User::factory()->create());

    postJson(route('clipping.store'), ['name' => 'Студия', 'driver' => 'unknown', 'api_key' => 'fixture-only'])
        ->assertUnprocessable()->assertJsonValidationErrors('driver');

    expect(ClippingConnection::query()->exists())->toBeFalse();
});
