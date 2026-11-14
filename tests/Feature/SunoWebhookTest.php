<?php

use App\Events\AudioGenerated;
use App\Models\Prompt;
use Illuminate\Support\Facades\Event;

function sunoPayload(string $taskId, string $type = 'complete', ?string $url = 'https://cdn.example.com/audio.mp3'): array
{
    return ['code' => 200, 'msg' => 'success', 'data' => [
        'callbackType' => $type, 'task_id' => $taskId,
        'data' => [['id' => 'track-1', 'audio_url' => $url]],
    ]];
}

test('готовое аудио сохраняется только для соответствующего задания', function (): void {
    Event::fake([AudioGenerated::class]);
    $prompt = Prompt::factory()->create(['audio_id' => 'task-1']);
    $decoy = Prompt::factory()->create(['audio_id' => 'task-2']);

    $this->postJson(route('webhook.suno'), sunoPayload('task-1'))->assertOk()->assertContent('');

    expect($prompt->fresh()->audio_url)->toBe('https://cdn.example.com/audio.mp3')
        ->and($decoy->fresh()->audio_url)->toBeNull();
    Event::assertDispatched(AudioGenerated::class, fn(AudioGenerated $event) => $event->getPrompt()->is($prompt));
});

test('неготовое неизвестное или пустое аудио не изменяет идею', function (string $id, string $type, ?string $url): void {
    Event::fake([AudioGenerated::class]);
    $prompt = Prompt::factory()->create(['audio_id' => 'task-1']);
    $this->postJson(route('webhook.suno'), sunoPayload($id, $type, $url))->assertOk();
    expect($prompt->fresh()->audio_url)->toBeNull();
    Event::assertNotDispatched(AudioGenerated::class);
})->with([
    ['task-1', 'first', 'https://cdn.example.com/audio.mp3'],
    ['unknown', 'complete', 'https://cdn.example.com/audio.mp3'],
    ['task-1', 'complete', null],
]);

test('невалидный callback не сохраняет данные', function (): void {
    Event::fake([AudioGenerated::class]);
    $this->postJson(route('webhook.suno'), [])->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'msg', 'data']);
    Event::assertNotDispatched(AudioGenerated::class);
});
