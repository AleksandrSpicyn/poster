<?php

declare(strict_types=1);

use App\Actions\Shots\CancelShotGenerationAction;
use App\Actions\Shots\CheckShotGenerationAction;
use App\Actions\Shots\ReconcileShotGenerationsAction;
use App\Actions\Shots\RequestShotGenerationAction;
use App\Actions\Shots\ResolveUnknownShotGenerationAction;
use App\Actions\Shots\RetryShotDownloadAction;
use App\Actions\Shots\SubmitShotGenerationAction;
use App\Classes\Generators\ImageGenerator\ImageGenerator;
use App\Classes\Generators\VoiceSample;
use App\Data\Series\SeriesCastData;
use App\Data\Shots\ResolveShotGenerationData;
use App\Enums\BillingProduct;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\RevisionStatus;
use App\Enums\TransitionMode;
use App\Jobs\CheckShotGenerationJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use App\Tasks\Episodes\BuildEpisodeContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\mock;
use function Pest\Laravel\travel;
use function Tests\Support\consoleCommand;
use function Tests\Support\fakeCodexImages;
use function Tests\Support\serialOperator;
use function Tests\Support\serialReferenceMedia;
use function Tests\Support\serialVoiceWav;
use function Tests\Support\workspaceOwner;

test('выключение проекта блокирует ожидающий внешний этап', function (GenerationOperationStage $stage): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'disable-project-shot');
    $operation = $prompt->generationOperations()->sole();
    $operation->update(['stage' => $stage]);
    $prompt->project?->update(['is_active' => false]);

    app(SubmitShotGenerationAction::class)->run($operation->id);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($operation->cancel_requested_at)->not->toBeNull();
    expect($prompt->refresh()->generated)->toBeFalse();
    expect($prompt->generationOperations()->count())->toBe(1);
    Http::assertNothingSent();
})->with([GenerationOperationStage::IMAGE, GenerationOperationStage::VIDEO]);

test('повтор запроса возвращает тот же дубль без запуска внешних систем', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    Http::fake();

    $first = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'request-1');
    $second = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'request-1');

    expect($second->id)->toBe($first->id);
    expect($shot->prompts()->count())->toBe(1);
    expect($first->input_snapshot)->toMatchArray(['series_id' => $shot->scene?->episodeRevision?->episode?->series_id]);
    expect($first->generationOperations()->firstOrFail()->status)->toBe(GenerationOperationStatus::PLANNED);
    Bus::assertDispatched(SubmitShotGenerationJob::class);
    Http::assertNothingSent();
});

test('завершённый дубль хранится приватно без публикации и повторной отправки', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    consoleCommand('billing:shadow-usage', ['owner' => $actor->id, '--enable' => true])->assertSuccessful();
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake([
        '*contents/generations/tasks' => Http::response(['id' => 'remote-1']),
        '*contents/generations/tasks/remote-1' => Http::response(['status' => 'succeeded', 'usage' => ['completion_tokens' => 324900], 'generate_audio' => true, 'content' => ['video_url' => 'https://cdn.example.com/video.mp4']]),
        'https://cdn.example.com/video.mp4' => Http::response(File::get(base_path('tests/Fixtures/shot.mp4'))),
    ]);

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'complete-1');
    $image = $prompt->generationOperations()->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($image->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($video->id);
    app(SubmitShotGenerationAction::class)->run($video->id);

    travel(61)->seconds();
    app(CheckShotGenerationAction::class)->run($video->id);
    $download = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($download->id);

    expect($prompt->refresh()->generated)->toBeTrue();
    assertDatabaseCount('generation_reservations', 1);
    assertDatabaseHas('generation_reservations', ['owner_id' => $actor->id, 'prompt_id' => $prompt->id, 'request_hash' => $prompt->input_hash, 'status' => 'consumed']);
    assertDatabaseCount('generation_credit_entries', 0);
    assertDatabaseHas('generation_provider_usage', ['operation_id' => $video->id, 'external_id' => 'remote-1', 'completion_tokens' => 324900, 'charge_e8' => null]);
    expect($prompt->getFirstMedia(Prompt::SHOT_VIDEO)?->disk)->toBe('local');
    expect($prompt->getFirstMedia(Prompt::SHOT_IMAGE)?->disk)->toBe('local');
    expect($prompt->posts()->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('generated-images/serial-frame.png');
    Http::assertSentCount(3);
    Http::assertSent(function (Request $request): bool {
        $image = data_get($request->data(), 'content.1.image_url.url');

        return str_ends_with($request->url(), '/contents/generations/tasks')
            && is_string($image) && str_starts_with($image, 'data:image/png;base64,');
    });
    Bus::assertDispatched(CheckShotGenerationJob::class);
});

test('неизвестный запуск не повторяется и блокирует новый платный дубль', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    consoleCommand('billing:shadow-usage', ['owner' => $actor->id, '--enable' => true])->assertSuccessful();
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::failedConnection()]);
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'unknown-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();

    app(SubmitShotGenerationAction::class)->run($video->id);
    app(SubmitShotGenerationAction::class)->run($video->id);

    assertDatabaseHas('generation_reservations', ['prompt_id' => $prompt->id, 'status' => 'held']);
    expect($video->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'unknown-2', newTake: true))->toThrow(ValidationException::class);
    Http::assertSentCount(1);
});

test('изменившийся контекст останавливает платный этап', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake();
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'stale-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    $shot->scene?->episodeRevision?->episode?->series?->increment('specification_version');

    app(SubmitShotGenerationAction::class)->run($video->id);

    expect($video->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    Http::assertNothingSent();
});

test('reconciler восстанавливает доставку и не повторяет прерванную отправку', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'interrupted-1');
    $operation = $prompt->generationOperations()->firstOrFail();
    $operation->update(['status' => GenerationOperationStatus::SUBMITTING, 'updated_at' => now()->subMinutes(11)]);
    Bus::fake();
    Http::fake();

    app(ReconcileShotGenerationsAction::class)->run();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    Bus::assertNotDispatched(SubmitShotGenerationJob::class);
    Http::assertNothingSent();
});

test('отмена запланированного дубля предотвращает генерацию', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    consoleCommand('billing:shadow-usage', ['owner' => $actor->id, '--enable' => true])->assertSuccessful();
    Bus::fake();
    Http::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'cancel-1');
    $operation = $prompt->generationOperations()->firstOrFail();

    app(CancelShotGenerationAction::class)->run($prompt, $actor);
    app(SubmitShotGenerationAction::class)->run($operation->id);

    assertDatabaseHas('generation_reservations', ['prompt_id' => $prompt->id, 'status' => 'released']);
    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($prompt->refresh()->generated)->toBeFalse();
    Http::assertNothingSent();
});

test('продолжение использует финальный кадр готового предыдущего дубля', function (): void {
    ['shot' => $firstShot, 'actor' => $actor] = serialShotFixture();
    $shot = Shot::factory()->for($firstShot->scene ?? throw new RuntimeException('Scene missing.'))->create(['position' => 2, 'transition_mode' => TransitionMode::CONTINUE_SCENE]);
    $source = Prompt::factory()->recycle(workspaceOwner())->create(['project_id' => $firstShot->scene->episodeRevision?->episode?->series?->project_id, 'shot_id' => $firstShot->id, 'take_number' => 1, 'generated' => true]);
    $source->addMediaFromString('synthetic previous video')->usingFileName('previous.mp4')->toMediaCollection(Prompt::SHOT_VIDEO, 'local');
    Bus::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    Process::preventStrayProcesses();
    Process::fake(['*ffmpeg*' => function (PendingProcess $process): ProcessResult {
        $command = $process->command;
        expect($command)->toBeArray();

        if (! is_array($command)) {
            throw new RuntimeException('Expected an argument-vector command.');
        }

        $output = end($command);

        if (! is_string($output)) {
            throw new RuntimeException('Frame output path missing.');
        }

        File::copy(base_path('tests/Fixtures/portrait.png'), $output);

        return Process::result();
    }]);

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, $source->id, 'continue-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);

    expect($prompt->getFirstMedia(Prompt::SHOT_IMAGE)?->disk)->toBe('local');
    expect($prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->exists())->toBeTrue();
    Process::assertRan(fn(PendingProcess $process): bool => is_array($process->command) && in_array('-sseof', $process->command, true));
});

/** @return array{shot: Shot, actor: User} */
function serialShotFixture(): array
{
    Storage::fake('local');
    Storage::fake('public');
    config(['services.video.provider' => 'seedance', 'services.seedance.api_key' => 'test']);
    $series = Series::factory()->recycle(workspaceOwner())->create();
    $character = Character::factory()->for($series)->create();
    $version = CharacterRevision::factory()->for($character)->approved()->create(['voice' => ['tone' => 'calm and warm']]);
    $version->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(CharacterRevision::REFERENCES, 'local');
    $episode = Episode::factory()->recycle(workspaceOwner())->for($series)->create();
    $revision = EpisodeRevision::factory()->for($episode)->approved()->create(['context_snapshot' => ['specification_version' => $series->specification_version]]);
    $revision->characterRevisions()->attach($version);
    $episode->update(['current_revision_id' => $revision->id]);
    $scene = Scene::factory()->for($revision, 'episodeRevision')->create();

    return ['shot' => Shot::factory()->for($scene)->create(), 'actor' => serialOperator()];
}

function fakeSerialOpeningFrame(): void
{
    mock(ImageGenerator::class)->shouldReceive('generate')->once()->andReturnUsing(function (string $prompt, array $references): string {
        expect($prompt)->toContain('series visual style');
        expect($references)->not->toBeEmpty();
        Storage::disk('public')->put('generated-images/serial-frame.png', File::get(base_path('tests/Fixtures/portrait.png')));

        return Storage::disk('public')->url('generated-images/serial-frame.png');
    });
}

test('поздний результат отменённой задачи сохраняется без принятия дубля', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    consoleCommand('billing:shadow-usage', ['owner' => $actor->id, '--enable' => true])->assertSuccessful();
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake([
        '*contents/generations/tasks' => Http::response(['id' => 'cancelled-remote']),
        '*contents/generations/tasks/cancelled-remote' => Http::response(['status' => 'succeeded', 'generate_audio' => true, 'content' => ['video_url' => 'https://cdn.example.com/late.mp4']]),
        'https://cdn.example.com/late.mp4' => Http::response(File::get(base_path('tests/Fixtures/shot.mp4'))),
    ]);
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'late-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($video->id);

    app(CancelShotGenerationAction::class)->run($prompt, $actor);

    travel(61)->seconds();
    app(CheckShotGenerationAction::class)->run($video->id);
    $download = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($download->id);

    assertDatabaseHas('generation_reservations', ['prompt_id' => $prompt->id, 'status' => 'released']);
    expect($download->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($prompt->refresh()->generated)->toBeFalse();
    expect($prompt->getFirstMedia(Prompt::SHOT_VIDEO))->not->toBeNull();
    expect($prompt->posts()->exists())->toBeFalse();
    Http::assertSentCount(3);
});

test('сбой скачивания повторяет только загрузку готового результата', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    consoleCommand('billing:shadow-usage', ['owner' => $actor->id, '--enable' => true])->assertSuccessful();
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake([
        '*contents/generations/tasks' => Http::response(['id' => 'download-remote']),
        '*contents/generations/tasks/download-remote' => Http::response(['status' => 'succeeded', 'generate_audio' => true, 'content' => ['video_url' => 'https://cdn.example.com/retry.mp4']]),
        'https://cdn.example.com/retry.mp4' => Http::sequence()->push('unavailable', 503)->push(File::get(base_path('tests/Fixtures/shot.mp4'))),
    ]);
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'download-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($video->id);
    travel(61)->seconds();
    app(CheckShotGenerationAction::class)->run($video->id);
    $download = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->firstOrFail();

    app(SubmitShotGenerationAction::class)->run($download->id);
    expect($download->refresh()->status)->toBe(GenerationOperationStatus::PLANNED);
    assertDatabaseHas('generation_reservations', ['prompt_id' => $prompt->id, 'status' => 'held']);
    travel(61)->seconds();
    app(SubmitShotGenerationAction::class)->run($download->id);

    assertDatabaseCount('generation_reservations', 1);
    assertDatabaseHas('generation_reservations', ['prompt_id' => $prompt->id, 'status' => 'consumed']);
    expect($prompt->refresh()->generated)->toBeTrue();
    expect($download->refresh()->check_count)->toBe(2);
    Http::assertSentCount(4);
    expect(Http::recorded(fn(Request $request): bool => $request->method() === 'POST')->count())->toBe(1);
});

test('реплики и голос персонажа фиксируются в сценарном промпте', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $character = $shot->scene?->episodeRevision?->characterRevisions()->firstOrFail() ?? throw new RuntimeException('Character missing.');
    $shot->update(['dialogue' => [['character_id' => $character->character_id, 'text' => 'The adventure begins.']]]);
    Bus::fake();
    Http::fake();

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'dialogue-1');

    expect(data_get($prompt->input_snapshot, 'characters.0'))->not->toHaveKey('voice_sample');
    expect($prompt->prompt)->toContain('The adventure begins.', 'calm and warm', 'series language', 'pinned voice blueprint');
    expect(data_get($prompt->input_snapshot, 'video.generate_audio'))->toBeTrue();
    expect(data_get($prompt->input_snapshot, 'audio_mode'))->toBe('native');
    Http::assertNothingSent();
});

test('немой дубль фиксирует звук до очереди и сохраняет payload после смены конфигурации', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $revision = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail();
    $series = $revision->episode()->firstOrFail()->series()->firstOrFail();
    $series->update(['specification' => [...$series->specification, 'audio_mode' => 'silent']]);

    $revision->update(['context_snapshot' => [...$revision->context_snapshot, 'specification' => $series->specification]]);
    consoleCommand('billing:shadow-usage', ['owner' => $actor->id, '--enable' => true])->assertSuccessful();
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'silent-remote'])]);

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'silent-1');
    $snapshot = $prompt->input_snapshot;
    config(['services.video.provider' => 'runway', 'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615', 'services.seedance.resolution' => '480p']);
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($video->id);

    assertDatabaseCount('generation_reservations', 0);
    expect(data_get($snapshot, 'audio_mode'))->toBe('silent');
    expect(data_get($snapshot, 'video.generate_audio'))->toBeFalse();
    expect($prompt->refresh()->input_snapshot)->toEqual($snapshot);
    expect($video->refresh()->request_snapshot)->toEqual($snapshot);
    expect($video->status)->toBe(GenerationOperationStatus::SUBMITTED);
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($snapshot): bool {
        $text = data_get($request->data(), 'content.0.text');

        return $request->method() === 'POST'
            && $request['generate_audio'] === false
            && $request['model'] === data_get($snapshot, 'video.model')
            && $request['resolution'] === data_get($snapshot, 'video.resolution')
            && is_string($text) && str_contains($text, 'Completely silent video');
    });
});

test('закреплённый немой режим запрещает реплики до создания платного дубля', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $revision = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail();
    $character = $revision->characterRevisions()->firstOrFail();
    $revision->update(['context_snapshot' => [...$revision->context_snapshot, 'specification' => ['audio_mode' => 'silent']]]);
    $shot->update(['dialogue' => [['character_id' => $character->character_id, 'text' => 'No audio allowed.']]]);
    Bus::fake();
    Process::fake();

    expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'forbidden-silent-1'))
        ->toThrow(ValidationException::class);

    expect($shot->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('реплики native требуют поддержки звука до создания платного дубля', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $character = $shot->scene?->episodeRevision?->characterRevisions()->firstOrFail() ?? throw new RuntimeException('Character missing.');
    $shot->update(['dialogue' => [['character_id' => $character->character_id, 'text' => 'Speech needs audio support.']]]);
    config(['services.video.provider' => 'runway']);
    Bus::fake();
    Process::fake();

    expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'unsupported-native-1'))
        ->toThrow(ValidationException::class);

    expect($shot->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('истёкшее право инициатора блокирует ещё не отправленную задачу', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    Http::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'actor-1');
    $operation = $prompt->generationOperations()->firstOrFail();
    $actor->forceFill(['email_verified_at' => null])->save();

    app(SubmitShotGenerationAction::class)->run($operation->id);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    expect($operation->error_code)->toBe('invalid_actor_or_snapshot');
    Http::assertNothingSent();
});

test('источником служит ранний дубль исторической версии того же эпизода', function (int $sourcePosition, bool $allowed): void {
    ['shot' => $oldShot, 'actor' => $actor] = serialShotFixture();
    $oldShot->update(['position' => $sourcePosition]);
    $oldRevision = $oldShot->scene->episodeRevision ?? throw new RuntimeException('Revision missing.');
    $episode = $oldRevision->episode ?? throw new RuntimeException('Episode missing.');
    $source = Prompt::factory()->recycle(workspaceOwner())->create(['project_id' => $episode->series?->project_id, 'shot_id' => $oldShot->id, 'take_number' => 1, 'generated' => true]);
    $source->addMediaFromString('synthetic historical video')->usingFileName('historical.mp4')->toMediaCollection(Prompt::SHOT_VIDEO, 'local');
    $revision = EpisodeRevision::factory()->for($episode)->approved()->create(['context_snapshot' => ['specification_version' => 1]]);
    $revision->characterRevisions()->attach($oldRevision->characterRevisions->modelKeys());
    $episode->update(['current_revision_id' => $revision->id]);
    $scene = Scene::factory()->for($revision, 'episodeRevision')->create();
    $shot = Shot::factory()->for($scene)->create(['position' => 2, 'transition_mode' => TransitionMode::CONTINUE_SCENE]);
    Bus::fake();
    Http::fake();

    if (! $allowed) {
        expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, $source->id, 'history-1'))->toThrow(ValidationException::class);
        expect($shot->prompts()->exists())->toBeFalse();
        Http::assertNothingSent();

        return;
    }

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, $source->id, 'history-1');

    expect(data_get($prompt->input_snapshot, 'source_prompt_id'))->toBe($source->id);
    expect(data_get($prompt->input_snapshot, 'episode_revision_id'))->toBe($revision->id);
    Http::assertNothingSent();
})->with(['ранний кадр' => [1, true], 'будущий кадр' => [3, false]]);

test('подтверждение неизвестной задачи читает готовый результат без новой оплаты', function (): void {
    ['operation' => $operation, 'actor' => $actor, 'prompt' => $prompt] = unknownSerialVideoFixture();
    Http::fake(['*contents/generations/tasks/found-remote' => Http::response([
        'status' => 'succeeded', 'generate_audio' => true,
        'model' => data_get($operation->request_snapshot, 'video.model'),
        'content' => ['video_url' => 'https://cdn.example.com/found.mp4'],
    ])]);
    $data = new ResolveShotGenerationData('found-remote', 'Verified in the provider console.');

    app(ResolveUnknownShotGenerationAction::class)->run($operation, $data, $actor);
    app(ResolveUnknownShotGenerationAction::class)->run($operation, $data, $actor);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUCCEEDED);
    assertDatabaseCount('generation_provider_usage', 1);
    assertDatabaseHas('generation_provider_usage', ['operation_id' => $operation->id, 'prompt_id' => $prompt->id, 'external_id' => 'found-remote', 'completion_tokens' => null]);
    expect($operation->external_id)->toBe('found-remote');
    expect(data_get($operation->result_snapshot, 'resolved_by'))->toBe($actor->id);
    expect($prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->count())->toBe(1);
    Http::assertSentCount(1);
    Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
});

test('подтверждение чужой модели сохраняет неизвестный исход', function (): void {
    ['operation' => $operation, 'actor' => $actor, 'prompt' => $prompt] = unknownSerialVideoFixture();
    Http::fake(['*contents/generations/tasks/wrong-model' => Http::response([
        'status' => 'succeeded', 'generate_audio' => true, 'model' => 'another-model',
        'content' => ['video_url' => 'https://cdn.example.com/another.mp4'],
    ])]);

    expect(fn() => app(ResolveUnknownShotGenerationAction::class)->run($operation, new ResolveShotGenerationData('wrong-model'), $actor))->toThrow(ValidationException::class);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUBMISSION_UNKNOWN);
    assertDatabaseMissing('generation_provider_usage', ['external_id' => 'wrong-model']);
    expect($operation->external_id)->toBeNull();
    expect($prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->exists())->toBeFalse();
    Http::assertSentCount(1);
    Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
});

test('подтверждённая ожидающая задача получает новый ограниченный срок опроса', function (): void {
    ['operation' => $operation, 'actor' => $actor] = unknownSerialVideoFixture();
    $operation->update(['check_count' => 62]);
    Http::fake(['*contents/generations/tasks/still-running' => Http::response(['status' => 'running'])]);

    app(ResolveUnknownShotGenerationAction::class)->run($operation, new ResolveShotGenerationData('still-running'), $actor);
    travel(61)->seconds();
    app(CheckShotGenerationAction::class)->run($operation->id);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::RUNNING);
    expect($operation->check_count)->toBe(63);
    expect(data_get($operation->result_snapshot, 'polling_limit'))->toBe(123);
    Http::assertSentCount(2);
    Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
});

test('оператор повторяет только остановленную загрузку того же результата', function (): void {
    ['operation' => $operation, 'actor' => $actor, 'prompt' => $prompt] = unknownSerialVideoFixture();
    Http::fake([
        '*contents/generations/tasks/download-only' => Http::response([
            'status' => 'succeeded', 'generate_audio' => true,
            'model' => data_get($operation->request_snapshot, 'video.model'),
            'content' => ['video_url' => 'https://cdn.example.com/download-only.mp4'],
        ]),
        'https://cdn.example.com/download-only.mp4' => Http::response(File::get(base_path('tests/Fixtures/shot.mp4'))),
    ]);
    app(ResolveUnknownShotGenerationAction::class)->run($operation, new ResolveShotGenerationData('download-only'), $actor);
    $download = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->firstOrFail();
    $download->update(['status' => GenerationOperationStatus::FAILED, 'check_count' => 3]);

    $first = app(RetryShotDownloadAction::class)->run($prompt, $actor);
    $second = app(RetryShotDownloadAction::class)->run($prompt, $actor);
    app(SubmitShotGenerationAction::class)->run($download->id);

    expect($first->id)->toBe($second->id);
    expect($download->refresh()->status)->toBe(GenerationOperationStatus::SUCCEEDED);
    expect($download->check_count)->toBe(4);
    expect(data_get($download->result_snapshot, 'retry_limit'))->toBe(6);
    expect($prompt->refresh()->generated)->toBeTrue();
    Http::assertSentCount(2);
    Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
});

test('один ключ с другим источником не заменяет исходный запрос', function (): void {
    ['shot' => $firstShot, 'actor' => $actor] = serialShotFixture();
    $scene = $firstShot->scene ?? throw new RuntimeException('Scene missing.');
    $secondShot = Shot::factory()->for($scene)->create(['position' => 2]);
    $target = Shot::factory()->for($scene)->create(['position' => 3, 'transition_mode' => TransitionMode::CONTINUE_SCENE]);
    $sources = collect([$firstShot, $secondShot])->map(function (Shot $shot): Prompt {
        $source = Prompt::factory()->recycle(workspaceOwner())->create(['project_id' => $shot->scene?->episodeRevision?->episode?->series?->project_id,
            'shot_id' => $shot->id, 'take_number' => 1, 'generated' => true]);
        $source->addMediaFromString('synthetic source video')->usingFileName('source.mp4')->toMediaCollection(Prompt::SHOT_VIDEO, 'local');

        return $source;
    });
    Bus::fake();
    Http::fake();
    $first = $sources->firstOrFail();
    $second = $sources->last() ?? throw new RuntimeException('Source missing.');

    $prompt = app(RequestShotGenerationAction::class)->run($target, $actor, $first->id, 'same-key');
    expect(fn() => app(RequestShotGenerationAction::class)->run($target, $actor, $second->id, 'same-key'))->toThrow(ValidationException::class);

    expect($target->prompts()->count())->toBe(1);
    expect(data_get($prompt->refresh()->input_snapshot, 'source_prompt_id'))->toBe($first->id);
    Http::assertNothingSent();
});

/** @return array{operation: GenerationOperation, actor: User, prompt: Prompt} */
function unknownSerialVideoFixture(): array
{
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    fakeSerialOpeningFrame();
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'operator-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $operation = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    $operation->update(['status' => GenerationOperationStatus::SUBMISSION_UNKNOWN]);

    return ['operation' => $operation, 'actor' => $actor, 'prompt' => $prompt];
}

test('подтверждённый пользователь без операторского доступа не создаёт дубль', function (): void {
    ['shot' => $shot] = serialShotFixture();
    $actor = User::factory()->create();
    Bus::fake();
    Http::fake();

    expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'forbidden-1'))->toThrow(AuthorizationException::class);

    expect($shot->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('синхронный запуск проверяет свежее право инициатора', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    User::query()->whereKey($actor->id)->update(['email_verified_at' => null]);
    Bus::fake();
    Http::fake();

    expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'stale-actor-1'))->toThrow(AuthorizationException::class);

    expect($shot->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('отзыв операторского доступа после очереди предотвращает внешний запуск', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    Http::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'revoked-operator-1');
    $operation = $prompt->generationOperations()->firstOrFail();
    $actor->forceFill(['email_verified_at' => null])->save();

    app(SubmitShotGenerationAction::class)->run($operation->id);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::FAILED);
    expect($operation->error_code)->toBe('invalid_actor_or_snapshot');
    Http::assertNothingSent();
});

test('недопустимая длительность возвращает ошибку до сохранения платной задачи', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    config(['services.seedance.model' => 'dreamina-seedance-2-0-mini-260615']);
    $shot->update(['duration_ms' => 20000]);
    Bus::fake();
    Http::fake();

    expect(fn() => app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'duration-1'))->toThrow(ValidationException::class);

    expect($shot->prompts()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('reconciler продолжает после сохранённого кадра без повторной генерации', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    Http::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'recovered-image-1');
    $image = $prompt->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(Prompt::SHOT_IMAGE, 'local');
    $operation = $prompt->generationOperations()->firstOrFail();
    $operation->update(['status' => GenerationOperationStatus::SUBMITTING, 'updated_at' => now()->subMinutes(11)]);
    Bus::fake();

    app(ReconcileShotGenerationsAction::class)->run();

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUCCEEDED);
    expect(data_get($operation->result_snapshot, 'image_media_id'))->toBe($image->id);
    expect($prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->count())->toBe(1);
    Bus::assertDispatched(SubmitShotGenerationJob::class);
    Http::assertNothingSent();
});

test('занятая авторизация откладывает тот же дубль и продолжает после освобождения', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    Bus::fake();
    Http::fake();
    fakeCodexImages();
    $home = realpath(config()->string('services.codex.home'));

    if ($home === false) {
        throw new RuntimeException('Codex fixture home missing.');
    }

    $lock = Cache::lock('codex:' . hash('sha256', $home), 120);
    expect($lock->get())->toBeTrue();
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'busy-image-1');
    $operation = $prompt->generationOperations()->firstOrFail();

    try {
        app(SubmitShotGenerationAction::class)->run($operation->id);

        expect($operation->refresh()->status)->toBe(GenerationOperationStatus::PLANNED);
        expect($operation->next_check_at?->isFuture())->toBeTrue();
        expect($operation->error_code)->toBeNull();
        expect($shot->prompts()->count())->toBe(1);
        expect($prompt->generationOperations()->count())->toBe(1);
        Process::assertNothingRan();
        Http::assertNothingSent();
    } finally {
        $lock->release();
    }

    travel(61)->seconds();
    app(SubmitShotGenerationAction::class)->run($operation->id);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::SUCCEEDED);
    expect($shot->prompts()->count())->toBe(1);
    expect($prompt->getMedia(Prompt::SHOT_IMAGE))->toHaveCount(1);
    expect($prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->count())->toBe(1);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
    Http::assertNothingSent();
});

test('передаёт выбранный голос в реальный запрос видео только при включённом флажке', function (bool $enabled, string $lead): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $revision = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    $series = $episode->series()->firstOrFail();
    $character = $revision->characterRevisions()->firstOrFail();
    $character->character()->firstOrFail()->update(['current_revision_id' => $character->id]);

    $leadId = $lead === 'none' ? null : $character->character_id;

    if ($lead === 'other') {
        $other = Character::factory()->for($series)->create();
        $version = CharacterRevision::factory()->for($other)->approved()->create();
        $other->update(['current_revision_id' => $version->id]);
        $leadId = $other->id;
    }

    $series->update(['cast_settings' => new SeriesCastData($leadId, 'Bright baritone with short pauses', $enabled)]);
    $pinned = app(BuildEpisodeContext::class)->run($episode);
    $revision->update(['context_snapshot' => $pinned]);
    $series->update(['cast_settings' => new SeriesCastData($character->character_id, 'Different voice for future episodes', true)]);
    $shot->update(['dialogue' => [['character_id' => $character->character_id, 'text' => 'Here is the letter.']]]);
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'fake-voice'])]);

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'pinned-series-voice');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail()->id);

    expect($pinned['lead_character_id'] ?? null)->toBe($leadId);
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($enabled, $lead): bool {
        $text = data_get($request->data(), 'content.0.text');

        return $request->method() === 'POST' && $request['generate_audio'] === true && is_string($text)
            && str_contains($text, 'Here is the letter.')
            && str_contains($text, 'Bright baritone with short pauses') === ($enabled && $lead !== 'other') && str_contains($text, 'calm and warm') && ! str_contains($text, 'Different voice for future episodes');
    });
})->with([true, false])->with(['speaker', 'other', 'none']);

test('восемь секунд купленного пакета списываются после доставки вертикального видео', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $shot->update(['duration_ms' => 8000]);
    config(['app.url' => 'https://poster.example', 'billing.merchants.sandbox.shop' => 'pilot', 'billing.merchants.sandbox.credentials.secret' => 'test']);
    consoleCommand('billing:pilot', ['owner' => $actor->id, '--mode' => 'enforced', '--sales' => 'on'])->assertSuccessful();
    Bus::fake();
    fakeSerialOpeningFrame();
    $file = Storage::disk('local')->path('eight-seconds.mp4');
    Process::timeout(30)->run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-f', 'lavfi', '-i', 'color=size=496x864:rate=5', '-f', 'lavfi', '-i', 'anullsrc',
        '-t', '8', '-c:v', 'libx264', '-preset', 'ultrafast', '-threads', '1', '-c:a', 'aac', '-y', $file])->throw();
    Http::fake([
        '*contents/generations/tasks' => Http::response(['id' => 'paid-video']),
        '*contents/generations/tasks/paid-video' => Http::response(['status' => 'succeeded', 'generate_audio' => true, 'duration' => 8, 'content' => ['video_url' => 'https://cdn.example.test/clip.mp4']]),
        'https://cdn.example.test/clip.mp4' => Http::response(File::get($file)),
        '*/me' => Http::response(['account_id' => 'pilot', 'test' => true]),
        '*/payments*' => function (Request $request) {
            if ($request->method() === 'POST') {
                /** @var array{attempt_id: string, order_id: string} $meta */
                $meta = $request->data()['metadata'];

                return Http::response(['id' => $meta['attempt_id'], 'recipient' => ['account_id' => 'pilot'], 'test' => true, 'status' => 'pending', 'amount' => $request['amount'], 'metadata' => $meta]);
            }

            $id = basename($request->url());
            /** @var object{order_id: int, amount_kopecks: int} $payment */
            $payment = DB::table('billing_payments')->where('id', $id)->firstOrFail();

            return Http::response(['id' => $id, 'recipient' => ['account_id' => 'pilot'], 'test' => true, 'status' => 'succeeded', 'paid' => true,
                'amount' => ['currency' => 'RUB', 'value' => number_format($payment->amount_kopecks / 100, 2, '.', '')], 'metadata' => ['order_id' => (string) $payment->order_id, 'attempt_id' => $id]]);
        },
    ]);
    $buy = function (string $product) use ($actor): void {
        actingAs($actor)->post(route('billing.orders.store', $product), ['request_key' => (string) Str::uuid(), 'catalog_version' => BillingProduct::CATALOG_VERSION])->assertSessionHasNoErrors();
        travel(61)->seconds();
        consoleCommand('billing:sync-checkouts')->assertSuccessful();
    };
    $buy('one_off');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'paid-eight');
    assertDatabaseHas('generation_grants', ['owner_id' => $actor->id, 'available_seconds' => 22, 'held_seconds' => 8]);
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->where('stage', GenerationOperationStage::IMAGE)->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($video->id);
    travel(61)->seconds();
    app(CheckShotGenerationAction::class)->run($video->id);
    expect([$video->refresh()->status->value, $video->error_code, $video->external_id])->toBe(['succeeded', null, 'paid-video']);
    $delivery = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($delivery->id);
    app(SubmitShotGenerationAction::class)->run($delivery->id);

    expect($prompt->refresh()->generated)->toBeTrue();
    assertDatabaseHas('generation_grants', ['owner_id' => $actor->id, 'available_seconds' => 22, 'held_seconds' => 0, 'consumed_seconds' => 8]);
    $buy('author');
    actingAs($actor)->get(route('billing.show'))->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('balance.available', 142)->where('balance.consumed', 8)->has('sources', 2));
    assertDatabaseHas('billing_accounts', ['owner_id' => $actor->id, 'renewal_consent' => false]);
    consoleCommand('billing:audit')->assertSuccessful();
});

test('закрепляет 480p в новых и существующих ревизиях и передаёт его BytePlus', function (bool $newRevision): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $revision = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    config(['services.seedance.resolution' => '480p',
        'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615']);

    if ($newRevision) {
        $revision->update(['context_snapshot' => app(BuildEpisodeContext::class)->run($episode)]);
    }

    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'fake-resolution'])]);

    $take = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'pilot-resolution');
    app(SubmitShotGenerationAction::class)->run($take->generationOperations()->firstOrFail()->id);
    app(SubmitShotGenerationAction::class)->run($take->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail()->id);

    $expected = '480p';
    expect(data_get($take->input_snapshot, 'video.resolution'))->toBe($expected);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'POST' && $request['resolution'] === $expected);
})->with([true, false]);

test('дубль передаёт закреплённую запись только при реплике её владельца', function (bool $speaks): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $cast = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail()->characterRevisions()->sole();
    $wav = serialVoiceWav();
    $cast->addMediaFromString($wav)->usingFileName('voice.wav')
        ->withCustomProperties(['duration_ms' => 3000, 'checksum' => hash('sha256', $wav)])
        ->toMediaCollection(CharacterRevision::VOICE_SAMPLE);
    $shot->update(['dialogue' => $speaks ? [['character_id' => $cast->character_id, 'text' => 'Откройте мост.']] : []]);
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'voice-take'])]);

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'voice-sample');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->sole()->id);
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->sole()->id);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($speaks, $wav, $cast): bool {
        $content = $request['content'];

        if (! is_array($content)) {
            return false;
        }

        $spokenText = data_get($content, '0.text');

        if (! $speaks) {
            return count($content) === 2 && data_get($content, '1.role') === 'first_frame';
        }

        return count($content) === 3 && data_get($content, '1.role') === 'reference_image' && data_get($content, '2.role') === 'reference_audio'
            && data_get($content, '2.audio_url.url') === 'data:audio/wav;base64,' . base64_encode($wav)
            && is_string($spokenText) && str_contains($spokenText, 'Audio 1 is the voice of ' . $cast->character?->name)
            && str_contains($spokenText, 'Откройте мост.');
    });
})->with([true, false]);

test('ожидает согласования первого кадра и отправляет видео один раз', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    $shot->update(['camera' => [...$shot->camera, 'direction' => ['review_frame' => true]]]);
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'reviewed-take'])]);
    $take = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'approve-opening');
    $submit = app(SubmitShotGenerationAction::class);
    $submit->run($take->generationOperations()->firstOrFail()->id);

    $video = $take->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();

    $submit->run($video->id);

    expect($video->refresh()->status)->toBe(GenerationOperationStatus::FRAME_REVIEW);
    Http::assertNothingSent();
    $scene = $shot->scene()->firstOrFail();
    $revision = $scene->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    $url = route('shots.frame.approve', ['series' => $episode->series_id, 'episode' => $episode, 'revision' => $revision, 'scene' => $scene, 'shot' => $shot, 'prompt' => $take, 'operation' => $video]);
    actingAs($actor)->post($url)->assertSessionHasNoErrors()->assertRedirect();
    actingAs($actor)->post($url)->assertSessionHasNoErrors()->assertRedirect();

    $submit->run($video->id);
    $submit->run($video->id);

    expect($video->refresh()->status)->toBe(GenerationOperationStatus::SUBMITTED);
    Http::assertSentCount(1);
});

test('отправляет первые 15 секунд длинной пробы говорящего персонажа', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    config(['services.seedance.model' => 'dreamina-seedance-2-0-mini-260615']);
    $character = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail()->characterRevisions()->sole();
    $prefix = str_repeat(pack('v*', 500, 65036), 180000);
    $wav = VoiceSample::fromPcm($prefix . str_repeat(pack('v', 1000), 129120))->wav;
    $sample = $character->addMediaFromString($wav)
        ->usingFileName('voice.wav')
        ->withCustomProperties(['duration_ms' => 20380, 'checksum' => hash('sha256', $wav)])
        ->toMediaCollection(CharacterRevision::VOICE_SAMPLE);
    $shot->update(['dialogue' => [['character_id' => $character->character_id, 'text' => 'Вы можете вернуть моего папу?']]]);
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'long-voice'])]);

    $take = app(RequestShotGenerationAction::class)->run($shot, $actor);
    $submit = app(SubmitShotGenerationAction::class);
    $submit->run($take->generationOperations()->sole()->id);
    $submit->run($take->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->sole()->id);

    Http::assertSentCount(1);
    Http::assertSent(fn(Request $request): bool => data_get($request['content'], '2.audio_url.url')
        === 'data:audio/wav;base64,' . base64_encode(VoiceSample::fromPcm($prefix)->wav));
    expect(File::get($sample->getPath()))->toBe($wav);
});

test('два голоса остаются за своими репликами после смены версии персонажа', function (int $seconds, string $model, int $expectedSeconds): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
    config(['services.seedance.model' => $model]);
    $script = $shot->scene()->firstOrFail()->episodeRevision()->firstOrFail();
    $series = $script->episode()->firstOrFail()->series()->firstOrFail();
    $first = $script->characterRevisions()->sole();
    $firstWav = serialVoiceWav();
    $first->addMediaFromString($firstWav)
        ->usingFileName('first.wav')
        ->withCustomProperties(['duration_ms' => 3000, 'checksum' => hash('sha256', $firstWav)])
        ->toMediaCollection(CharacterRevision::VOICE_SAMPLE);
    $character = Character::factory()->for($series)->create();
    $draft = CharacterRevision::factory()->for($character)->create();
    serialReferenceMedia($draft);
    $params = ['project' => $series->project_id, 'series' => $series, 'character' => $character];
    $revisionParams = [...$params, 'revision' => $draft];
    $upload = fn(int $seconds): UploadedFile => UploadedFile::fake()->createWithContent('sample.wav', serialVoiceWav($seconds));

    actingAs($actor)->post(route('characters.references.store', $revisionParams), ['view' => 'voice', 'file' => $upload($seconds), 'lock_version' => 0])
        ->assertSessionHasNoErrors();
    actingAs($actor)->post(route('characters.revisions.approve', $revisionParams), ['lock_version' => 1])
        ->assertSessionHasNoErrors();
    $script->characterRevisions()->attach($draft);
    $secondWav = File::get($draft->refresh()->getFirstMediaPath(CharacterRevision::VOICE_SAMPLE));
    $expectedWav = $secondWav;

    if ($seconds > $expectedSeconds) {
        $pcm = substr($secondWav, 44, $expectedSeconds * 48000);
        $expectedWav = VoiceSample::fromPcm($pcm)->wav;
    }

    $shot->update(['dialogue' => [
        ['character_id' => $character->id, 'text' => 'Кто на мосту?'],
        ['character_id' => $first->character_id, 'text' => 'Это я.'],
    ]]);
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'dialogue'])]);
    $take = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'two-speakers');

    actingAs($actor)->post(route('characters.revisions.store', $params), ['description' => 'Новый образ'])
        ->assertSessionHasNoErrors();
    $new = $character->revisions()->latest('number')->firstOrFail();
    $oldSample = $draft->getFirstMedia(CharacterRevision::VOICE_SAMPLE);
    actingAs($actor)
        ->post(route('characters.references.store', $revisionParams), [
            'view' => 'voice',
            'file' => $upload(3),
            'replace_voice_id' => $oldSample?->id,
            'lock_version' => $draft->lock_version,
        ])
        ->assertSessionHasNoErrors();

    $replacement = $draft->refresh()->getMedia(CharacterRevision::VOICE_SAMPLE)->sole();
    expect($draft->status)->toBe(RevisionStatus::APPROVED)
        ->and($character->refresh()->current_revision_id)->toBe($draft->id)
        ->and($replacement->id)->not->toBe($oldSample?->id);
    $nextShot = Shot::factory()->create(['scene_id' => $shot->scene_id, 'position' => 2, 'dialogue' => $shot->dialogue]);
    $nextTake = app(RequestShotGenerationAction::class)->run($nextShot, $actor, requestKey: 'changed-voice');
    expect(data_get($nextTake->input_snapshot, 'characters.1.voice_sample.media_id'))->toBe($replacement->id);

    $newParams = [...$params, 'revision' => $new];
    actingAs($actor)->post(route('characters.references.store', $newParams), [
        'view' => 'voice', 'file' => $upload(5), 'lock_version' => $new->lock_version,
        'replace_voice_id' => $new->getFirstMedia(CharacterRevision::VOICE_SAMPLE)?->id,
    ])->assertSessionHasNoErrors();
    serialReferenceMedia($new);
    actingAs($actor)->post(route('characters.revisions.approve', $newParams), ['lock_version' => $new->refresh()->lock_version])
        ->assertSessionHasNoErrors();
    $submit = app(SubmitShotGenerationAction::class);
    $submit->run($take->generationOperations()->sole()->id);
    $submit->run($take->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->sole()->id);

    expect(data_get($take->input_snapshot, 'characters.1.voice_sample.duration_ms'))->toBe($seconds * 1000)
        ->and(data_get($take->input_snapshot, 'characters.1.voice_sample.sha256'))->toBe(hash('sha256', $secondWav));
    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($first, $character, $firstWav, $expectedWav): bool {
        $text = data_get($request['content'], '0.text');

        return is_string($text) && str_contains($text, 'Кто на мосту?') && str_contains($text, 'Это я.')
            && str_contains($text, 'Audio 1 is the voice of ' . $first->character?->name . ' (character_id ' . $first->character_id . ')')
            && str_contains($text, 'Audio 2 is the voice of ' . $character->name . ' (character_id ' . $character->id . ')')
            && data_get($request['content'], '2.audio_url.url') === 'data:audio/wav;base64,' . base64_encode($firstWav)
            && data_get($request['content'], '3.audio_url.url') === 'data:audio/wav;base64,' . base64_encode($expectedWav);
    });
})->with([
    'короткие пробы' => [4, 'dreamina-seedance-2-0-mini-260615', 4],
    '3 и 12 секунд в общем лимите Mini' => [21, 'dreamina-seedance-2-0-mini-260615', 12],
    'максимум 15 секунд на голос у модели 2.5' => [21, 'dreamina-seedance-2-5-260628', 15],
]);
