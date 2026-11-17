<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\Shot;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\serialReferenceMedia;

test('выключенный проект не создаёт новый дубль', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture();
    $context['episode']->series?->project?->update(['is_active' => false]);
    Bus::fake();

    actingAs($context['user'])->postJson(route('shots.generate', $context['params']), ['request_key' => 'disabled-shot', 'new_take' => false])
        ->assertUnprocessable();

    expect($context['shot']->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('запуск дубля создаёт долговечный intent до внешней генерации', function (): void {
    config([
        'services.video.provider' => VideoProvider::SEEDANCE->value,
        'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615',
        'services.seedance.resolution' => '720p',
    ]);
    Storage::fake('local');
    $context = episodeHttpFixture();
    $character = $context['revision']->characterRevisions()->firstOrFail();
    serialReferenceMedia($character);
    Bus::fake();
    Process::fake();

    actingAs($context['user'])
        ->post(route('shots.generate', $context['params']), ['request_key' => 'http-shot-request', 'new_take' => false])
        ->assertRedirect(route('episodes.show', ['series' => $context['episode']->series_id, 'episode' => $context['episode']->id]))
        ->assertSessionHasNoErrors();

    $take = $context['shot']->prompts()->firstOrFail();
    expect($take->generationOperations()->first()?->status->value)->toBe('planned');
    expect($take->generated)->toBeFalse();
    Bus::assertDispatched(SubmitShotGenerationJob::class);
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('неутверждённый сценарий не создаёт платный дубль', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture(false);
    Bus::fake();

    actingAs($context['user'])
        ->postJson(route('shots.generate', $context['params']), [])
        ->assertUnprocessable();

    expect($context['shot']->prompts()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('дубль получает локальную цель и финал только последнего плана', function (int $position, bool $receivesFinalState): void {
    Storage::fake('local');
    $context = episodeHttpFixture();
    serialReferenceMedia($context['revision']->characterRevisions()->firstOrFail());
    $series = $context['episode']->series()->firstOrFail();
    $series->update(['specification' => [...$series->specification,
        'visual_style' => 'Warm clay animation',
        'narrative_rules' => 'No dialogue or narration.',
        'story_outline' => 'Next season the cat chases a brass bell.',
    ]]);
    $context['scene']->update([
        'objective' => 'Shelter the paper boat.',
        'initial_state' => ['description' => 'Water flows toward a paper boat.'],
        'expected_final_state' => ['description' => 'The paper boat reaches the tray.'],
    ]);
    $context['shot']->update(['action' => 'The cat tilts a leaf toward the tray.']);
    $lastShot = Shot::factory()
        ->for($context['scene'])
        ->create(['position' => 2, 'action' => 'The cat guides the boat into the tray.']);
    $shot = $position === 1 ? $context['shot'] : $lastShot;
    Bus::fake([SubmitShotGenerationJob::class]);
    Process::fake();

    actingAs($context['user'])
        ->postJson(route('shots.generate', [...$context['params'], 'shot' => $shot->id]), ['request_key' => 'local-story-' . $position, 'new_take' => false])
        ->assertRedirect();

    $take = $shot->prompts()->sole();
    expect(data_get($take->input_snapshot, 'image_prompt'))
        ->toContain('Shelter the paper boat.', 'Water flows toward a paper boat.', $shot->action, 'Warm clay animation', 'No dialogue or narration.');
    expect(data_get($take->input_snapshot, 'image_prompt'))
        ->not->toContain('The paper boat reaches the tray.', 'Next season the cat chases a brass bell.');
    expect(data_get($take->input_snapshot, 'video_prompt'))
        ->toContain('Shelter the paper boat.', $shot->action, 'No dialogue or narration.');
    expect(data_get($take->input_snapshot, 'video_prompt'))
        ->not->toContain('Next season the cat chases a brass bell.');
    expect(str_contains($take->prompt, 'The paper boat reaches the tray.'))->toBe($receivesFinalState);
    Bus::assertDispatched(SubmitShotGenerationJob::class);
    Process::assertNothingRan();
    Http::assertNothingSent();
})->with(['промежуточный план' => [1, false], 'последний план' => [2, true]]);
