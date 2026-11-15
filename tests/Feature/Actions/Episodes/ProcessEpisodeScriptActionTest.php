<?php

declare(strict_types=1);

use App\Actions\Episodes\ProcessEpisodeScriptAction;
use App\Actions\Episodes\ReconcileEpisodeScriptsAction;
use App\Actions\Episodes\RequestEpisodeScriptAction;
use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Enums\ScriptGenerationStatus;
use App\Jobs\GenerateEpisodeScriptJob;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

use function Tests\Support\fakeCodex;
use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('фоновый сценарий сохраняется один раз по исходному контексту', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    Queue::fake([GenerateEpisodeScriptJob::class]);
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');
    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);
    fakeCodex(function (PendingProcess $process) use ($character): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', json_encode(storyScriptPayload($character), JSON_THROW_ON_ERROR));

        return Process::result();
    });

    app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);
    app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::COMPLETED);
    expect($episode->revisions()->count())->toBe(1);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
});

test('поздний ответ не перезаписывает отредактированный эпизод', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    Queue::fake([GenerateEpisodeScriptJob::class]);
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');
    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);
    fakeCodex(function (PendingProcess $process) use ($character, $episode): ProcessResult {
        $episode->increment('lock_version');
        File::put(($process->path ?? '') . '/result.json', json_encode(storyScriptPayload($character), JSON_THROW_ON_ERROR));

        return Process::result();
    });

    app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::FAILED);
    expect($episode->revisions()->exists())->toBeFalse();
    expect($episode->lock_version)->toBe(1);
});

test('черновик откатывается если request сменился до подтверждения результата', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    Queue::fake([GenerateEpisodeScriptJob::class]);
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');
    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);
    fakeCodex(function (PendingProcess $process) use ($character, $episode): ProcessResult {
        $episode->update(['script_request_key' => 'f1b5f5f8-1cca-436c-ab17-2764108f4ccd']);
        File::put(($process->path ?? '') . '/result.json', json_encode(storyScriptPayload($character), JSON_THROW_ON_ERROR));

        return Process::result();
    });

    app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);

    expect($episode->revisions()->exists())->toBeFalse();
    expect($episode->refresh()->current_revision_id)->toBeNull();
    expect($episode->lock_version)->toBe(0);
});

test('отзыв доступа после постановки в очередь блокирует текстовый запрос', function (): void {
    ['episode' => $episode] = storyFixture();
    $user = serialOperator();
    Queue::fake([GenerateEpisodeScriptJob::class]);
    Process::fake();
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');
    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);
    $user->forceFill(['email' => 'revoked@example.test'])->save();

    app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::FAILED);
    expect($episode->revisions()->exists())->toBeFalse();
    expect($user->hasVerifiedEmail())->toBeTrue();
    Process::assertNothingRan();
});

test('занятая авторизация сохраняет сценарный запрос для очереди без повторной квоты', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $user = serialOperator();
    Queue::fake([GenerateEpisodeScriptJob::class]);
    $data = new GenerateEpisodeScriptData('Find the key.', 0, '7e061dbf-290b-49ea-8ac3-cc69ed236884');
    app(RequestEpisodeScriptAction::class)->run($episode, $data, $user);
    fakeCodex(function (PendingProcess $process) use ($character): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', json_encode(storyScriptPayload($character), JSON_THROW_ON_ERROR));

        return Process::result();
    });
    $home = realpath(config()->string('services.codex.home'));

    if ($home === false) {
        throw new RuntimeException('Codex fixture home missing.');
    }

    $lock = Cache::lock('codex:' . hash('sha256', $home), 120);
    expect($lock->get())->toBeTrue();

    try {
        app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);

        expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::QUEUED);
        expect($episode->script_request_key)->toBe($data->request_key);
        expect($episode->script_generation_error)->toBeNull();
        expect($episode->revisions()->exists())->toBeFalse();
        Process::assertNothingRan();
    } finally {
        $lock->release();
    }

    app(ReconcileEpisodeScriptsAction::class)->run();
    app(ProcessEpisodeScriptAction::class)->run($episode, $data->request_key);

    expect($episode->refresh()->script_generation_status)->toBe(ScriptGenerationStatus::COMPLETED);
    expect($episode->revisions()->count())->toBe(1);
    Queue::assertPushed(GenerateEpisodeScriptJob::class, fn(GenerateEpisodeScriptJob $job): bool => $job->episodeId === $episode->id && $job->requestKey === $data->request_key);
    Process::assertRanTimes(fn(PendingProcess $process): bool => is_array($process->command) && in_array('exec', $process->command, true), 1);
});
