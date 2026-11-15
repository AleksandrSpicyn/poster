<?php

declare(strict_types=1);

use App\Actions\Episodes\GenerateEpisodeScriptAction;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Tests\Support\fakeCodex;
use function Tests\Support\serialOperator;
use function Tests\Support\storyFixture;
use function Tests\Support\storyScriptPayload;

test('генерирует структурированный черновик без изображения и видео', function (): void {
    ['episode' => $episode, 'character' => $character] = storyFixture();
    $payload = storyScriptPayload($character);
    Http::fake();
    fakeCodex(function (PendingProcess $process) use ($payload): ProcessResult {
        File::put(($process->path ?? '') . '/result.json', json_encode($payload, JSON_THROW_ON_ERROR));

        return Process::result();
    });

    $revision = app(GenerateEpisodeScriptAction::class)->run($episode, 'Find the key.', 0, serialOperator());

    expect($revision->title)->toBe('The locked door');
    expect($revision->scenes()->count())->toBe(1);
    expect(Prompt::query()->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('синхронная генерация проверяет оператора до вызова Codex', function (): void {
    ['episode' => $episode] = storyFixture();
    $user = User::factory()->create();
    config(['services.admin.email' => 'owner@example.test', 'horizon.allowed_emails' => []]);
    Process::fake();
    Http::fake();

    expect(fn() => app(GenerateEpisodeScriptAction::class)->run($episode, 'Find the key.', 0, $user))
        ->toThrow(AuthorizationException::class);

    expect($episode->revisions()->exists())->toBeFalse();
    Process::assertNothingRan();
    Http::assertNothingSent();
});
