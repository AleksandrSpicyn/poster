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
    Queue::fake();
    $context = episodeHttpFixture();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::READY, 'duration_ms' => 5000]);
    $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);

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

test('приёмка эпизода автоматически ставит экспорт 1080p перед публикацией', function (): void {
    Storage::fake('local');
    Queue::fake();
    $context = episodeHttpFixture();
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

test('приёмка реплик сохраняет подтверждение прослушивания конкретного монтажа', function (): void {
    Storage::fake('local');
    Queue::fake();
    $context = episodeHttpFixture();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->ready()->create(['manifest' => ['requires_audio_review' => true]]);
    $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::VIDEO);
    $payload = ['lock_version' => 0, 'confirmed_events' => 'Принят указ.', 'character_state' => 'Совет окончен.', 'open_threads' => 'Ждут вестей.', 'scene_closed' => true];
    $url = route('episodes.assemblies.accept', [...$context['params'], 'assembly' => $assembly]);

    actingAs($context['user'])->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('audio_reviewed');
    actingAs($context['user'])->post($url, [...$payload, 'audio_reviewed' => true])->assertSessionHasNoErrors();

    expect($assembly->refresh()->continuity_snapshot['audio_reviewed_by'])->toBe($context['user']->id);
});
