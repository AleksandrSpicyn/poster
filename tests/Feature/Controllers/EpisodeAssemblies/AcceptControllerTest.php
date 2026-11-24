<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

test('приёмка пилотного эпизода автоматически ставит экспорт 1080p перед публикацией', function (): void {
    Storage::fake('local');
    Queue::fake();
    $context = episodeHttpFixture();
    config(['services.upscale.series_id' => $context['episode']->series_id]);
    $manifest = ['shots' => [['media_id' => 1, 'prompt_id' => 1, 'checksum' => str_repeat('a', 64),
        'trim_start_ms' => 0, 'trim_end_ms' => 5000, 'duration_ms' => 5000]], 'duration_ms' => 5000];
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->ready()->create([
        'manifest' => $manifest, 'manifest_hash' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
    ]);
    $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    $params = [...$context['params'], 'assembly' => $assembly];

    actingAs($context['user'])->post(route('episodes.assemblies.accept', $params), [
        'lock_version' => 0, 'confirmed_events' => 'Key found.', 'character_state' => 'At the door.', 'open_threads' => 'The door is still locked.', 'scene_closed' => true,
    ])->assertSessionHasNoErrors();

    $operation = $assembly->upscales()->sole();
    expect($operation->profile_snapshot['export'])->toBe('1080p');
    Queue::assertPushed(AdvanceVideoUpscaleJob::class, fn(AdvanceVideoUpscaleJob $job): bool => $job->operationId === $operation->id);
    Http::assertNothingSent();
});
