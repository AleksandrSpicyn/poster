<?php

declare(strict_types=1);

use App\Events\AudioGenerated;
use App\Models\Prompt;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\postJson;

test('готовое аудио сохраняется только для соответствующего задания', function (): void {
    Event::fake([AudioGenerated::class]);

    $prompt = Prompt::factory()->create(['audio_id' => 'task-1']);
    $decoy = Prompt::factory()->create(['audio_id' => 'task-2']);

    postJson(route('webhook.suno'), sunoPayload('task-1'))
        ->assertOk()
        ->assertContent('');

    expect($prompt->refresh()->audio_url)
        ->toBe('https://cdn.example.com/audio.mp3')
        ->and($decoy->refresh()->audio_url)
        ->toBeNull();
    Event::assertDispatched(AudioGenerated::class, fn(AudioGenerated $event) => $event
        ->getPrompt()
        ->is($prompt));
});

test('неготовое неизвестное или пустое аудио не изменяет идею', function (string $id, string $type, ?string $url): void {
    Event::fake([AudioGenerated::class]);

    $prompt = Prompt::factory()->create(['audio_id' => 'task-1']);

    postJson(route('webhook.suno'), sunoPayload($id, $type, $url))->assertOk();
    expect($prompt->refresh()->audio_url)->toBeNull();
    Event::assertNotDispatched(AudioGenerated::class);
})->with([
    ['task-1', 'first', 'https://cdn.example.com/audio.mp3'],
    ['unknown', 'complete', 'https://cdn.example.com/audio.mp3'],
    ['task-1', 'complete', null],
]);

test('невалидный callback не сохраняет данные', function (): void {
    Event::fake([AudioGenerated::class]);

    postJson(route('webhook.suno'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'msg', 'data']);
    Event::assertNotDispatched(AudioGenerated::class);
});

test('повторный callback аудио не запускает сборку снова', function (): void {
    Event::fake([AudioGenerated::class]);

    Prompt::factory()->create(['audio_id' => 'task-1', 'audio_url' => 'https://cdn.example.com/audio.mp3']);

    postJson(route('webhook.suno'), sunoPayload('task-1'))->assertOk();

    Event::assertNotDispatched(AudioGenerated::class);
});

test('пустой список треков отклоняется без сохранения', function (): void {
    $payload = sunoPayload('task-1');
    $payload['data']['data'] = [];

    postJson(route('webhook.suno'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('data.data');
});

/** @return array{code: int, msg: string, data: array{callbackType: string, task_id: string, data: list<array{id: string, audio_url: string|null}>}} */
function sunoPayload(string $taskId, string $type = 'complete', ?string $url = 'https://cdn.example.com/audio.mp3'): array
{
    return ['code' => 200, 'msg' => 'success', 'data' => [
        'callbackType' => $type, 'task_id' => $taskId,
        'data' => [['id' => 'track-1', 'audio_url' => $url]],
    ]];
}
