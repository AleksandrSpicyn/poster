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
use App\Data\Shots\ResolveShotGenerationData;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\mock;
use function Pest\Laravel\travel;
use function Tests\Support\fakeCodexImages;
use function Tests\Support\serialOperator;

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
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake([
        '*contents/generations/tasks' => Http::response(['id' => 'remote-1']),
        '*contents/generations/tasks/remote-1' => Http::response(['status' => 'succeeded', 'generate_audio' => true, 'content' => ['video_url' => 'https://cdn.example.com/video.mp4']]),
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
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::failedConnection()]);
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'unknown-1');
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();

    app(SubmitShotGenerationAction::class)->run($video->id);
    app(SubmitShotGenerationAction::class)->run($video->id);

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
    Bus::fake();
    Http::fake();
    mock(ImageGenerator::class)->shouldNotReceive('generate');
    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'cancel-1');
    $operation = $prompt->generationOperations()->firstOrFail();

    app(CancelShotGenerationAction::class)->run($prompt, $actor);
    app(SubmitShotGenerationAction::class)->run($operation->id);

    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($prompt->refresh()->generated)->toBeFalse();
    Http::assertNothingSent();
});

test('продолжение использует финальный кадр готового предыдущего дубля', function (): void {
    ['shot' => $firstShot, 'actor' => $actor] = serialShotFixture();
    $shot = Shot::factory()->for($firstShot->scene ?? throw new RuntimeException('Scene missing.'))->create(['position' => 2, 'transition_mode' => TransitionMode::CONTINUE_SCENE]);
    $source = Prompt::factory()->create(['project_id' => $firstShot->scene->episodeRevision?->episode?->series?->project_id, 'shot_id' => $firstShot->id, 'take_number' => 1, 'generated' => true]);
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
    $series = Series::factory()->create();
    $character = Character::factory()->for($series)->create();
    $version = CharacterRevision::factory()->for($character)->approved()->create(['voice' => ['tone' => 'calm and warm']]);
    $version->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(CharacterRevision::REFERENCES, 'local');
    $episode = Episode::factory()->for($series)->create();
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

    expect($download->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($prompt->refresh()->generated)->toBeFalse();
    expect($prompt->getFirstMedia(Prompt::SHOT_VIDEO))->not->toBeNull();
    expect($prompt->posts()->exists())->toBeFalse();
    Http::assertSentCount(3);
});

test('сбой скачивания повторяет только загрузку готового результата', function (): void {
    ['shot' => $shot, 'actor' => $actor] = serialShotFixture();
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
    travel(61)->seconds();
    app(SubmitShotGenerationAction::class)->run($download->id);

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
    Bus::fake();
    fakeSerialOpeningFrame();
    Http::fake(['*contents/generations/tasks' => Http::response(['id' => 'silent-remote'])]);

    $prompt = app(RequestShotGenerationAction::class)->run($shot, $actor, requestKey: 'silent-1');
    $snapshot = $prompt->input_snapshot;
    config(['services.video.provider' => 'runway', 'services.seedance.model' => 'dreamina-seedance-2-0-mini-260615', 'services.seedance.resolution' => '480p']);
    app(SubmitShotGenerationAction::class)->run($prompt->generationOperations()->firstOrFail()->id);
    $video = $prompt->generationOperations()->where('stage', GenerationOperationStage::VIDEO)->firstOrFail();
    app(SubmitShotGenerationAction::class)->run($video->id);

    expect(data_get($snapshot, 'audio_mode'))->toBe('silent');
    expect(data_get($snapshot, 'video.generate_audio'))->toBeFalse();
    expect($prompt->refresh()->input_snapshot)->toBe($snapshot);
    expect($video->refresh()->request_snapshot)->toBe($snapshot);
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
    $source = Prompt::factory()->create(['project_id' => $episode->series?->project_id, 'shot_id' => $oldShot->id, 'take_number' => 1, 'generated' => true]);
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
        $source = Prompt::factory()->create(['project_id' => $shot->scene?->episodeRevision?->episode?->series?->project_id,
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
    User::query()->whereKey($actor->id)->update(['email' => 'revoked-actor@example.test']);
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
    config(['services.admin.email' => 'different-owner@example.test', 'horizon.allowed_emails' => []]);

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
