<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;
use function Pest\Laravel\artisan;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

pest()->beforeEach(function (): void {
    Http::preventStrayRequests();
})->in('Feature');

/** @return array{code: int, msg: string, data: array{callbackType: string, task_id: string, data: list<array{id: string, audio_url: string|null}>}} */
function sunoPayload(string $taskId, string $type = 'complete', ?string $url = 'https://cdn.example.com/audio.mp3'): array
{
    return ['code' => 200, 'msg' => 'success', 'data' => [
        'callbackType' => $type, 'task_id' => $taskId,
        'data' => [['id' => 'track-1', 'audio_url' => $url]],
    ]];
}

/** @param array<string, int|string> $arguments */
function consoleCommand(string $command, array $arguments = []): PendingCommand
{
    $pending = artisan($command, $arguments);
    assert($pending instanceof PendingCommand);

    return $pending;
}
