<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\consoleCommand;

test('подключение импортирует проекты и повторно обновляет те же клипы', function (): void {
    $owner = User::factory()->create();
    $catalog = ['projectId' => 'game', 'title' => 'Студия'];
    Http::fake([
        '*/clip-projects*' => Http::sequence()->push([$catalog])->push([$catalog])->push([$catalog, ['projectId' => 'next', 'title' => 'Студия']]),
        '*/exportable-clips*' => Http::response([['id' => 'clip']]),
        '*/post-tasks*' => Http::response(['data' => ['data' => []]]),
    ]);
    actingAs($owner);

    post(route('clipping.store'), ['name' => 'Моя площадка', 'driver' => 'opusclip', 'api_key' => 'fixture'])->assertRedirect();
    $connection = ClippingConnection::query()->sole();
    $project = Project::query()->sole();
    $clip = OpusClip::query()->sole();

    post(route('clipping.sync', $connection))->assertRedirect(route('clipping.edit', $connection));
    expect(Project::query()->sole()->id)->toBe($project->id)
        ->and(OpusClip::query()->sole()->id)->toBe($clip->id);

    consoleCommand('clipping:refresh')->assertSuccessful();

    expect(Project::query()->where('owner_id', $owner->id)->count())->toBe(2)
        ->and(OpusSource::query()->where('clipping_connection_id', $connection->id)->count())->toBe(2)
        ->and(OpusClip::query()->count())->toBe(2);
});

test('ошибка ключа видна владельцу и чужой источник нельзя обновить', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    Http::fake(['*/clip-projects*' => Http::response([], 403)]);

    actingAs(User::factory()->create())->post(route('clipping.sync', $connection))->assertNotFound();
    Http::assertNothingSent();

    actingAs($owner)->post(route('clipping.sync', $connection))->assertRedirect();

    expect($connection->refresh()->getAttribute('catalog_error'))->toContain('ключ')
        ->and(Project::query()->count())->toBe(0);
});
