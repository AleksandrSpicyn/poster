<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;

test('принятие монтажа подтверждает факты без публикации', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::READY, 'duration_ms' => 5000]);
    $assembly->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

    actingAs($context['user'])
        ->post(route('episodes.assemblies.accept', [...$context['params'], 'assembly' => $assembly]), ['lock_version' => 0, 'confirmed_events' => 'The key was found.', 'character_state' => 'The hero holds the key.', 'open_threads' => 'The door is still locked.', 'scene_closed' => false])
        ->assertRedirect();

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::ACCEPTED);
    expect($context['episode']->refresh()->accepted_assembly_id)->toBe($assembly->id);
    expect(data_get($assembly->continuity_snapshot, 'scene_closed'))->toBeFalse();
    Http::assertNothingSent();
});

test('принятие требует подтверждённых событий', function (): void {
    $context = episodeHttpFixture();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::READY]);

    actingAs($context['user'])
        ->postJson(route('episodes.assemblies.accept', [...$context['params'], 'assembly' => $assembly]), ['lock_version' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('confirmed_events');

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::READY);
});
