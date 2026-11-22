<?php

declare(strict_types=1);

use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\OpusClip;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\post;

test('предпросмотр сохраняет выбранные данные без импорта', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    Http::fake([
        '*/clip-projects*' => Http::response(['list' => [['projectId' => 'game', 'title' => 'Игра']]]),
        '*/exportable-clips*' => Http::response([['id' => 'A', 'title' => 'Финиш']]),
        '*/post-tasks*' => Http::response(['data' => ['data' => []]]),
    ]);

    actingAs($owner);
    $response = post(route('clipping.preview', $connection), ['external_id' => 'game']);

    $preview = ClippingImport::query()->sole();
    $response->assertRedirect(route('clipping.preview.show', [$connection, $preview->id]));
    expect($preview->clips[0]['title'])->toBe('Финиш')
        ->and($preview->imported_at)->toBeNull()
        ->and(OpusClip::query()->count())->toBe(0);
    assertDatabaseCount('projects', 0);
});

test('не допускает чужой проект при сопоставлении', function (): void {
    $owner = User::factory()->create();
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    $other = Project::factory()->create();
    actingAs($owner);

    post(route('clipping.preview', $connection), ['external_id' => 'game', 'project_id' => $other->id])->assertNotFound();

    Http::assertNothingSent();
    assertDatabaseCount('clipping_imports', 0);
});
