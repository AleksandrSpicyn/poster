<?php

declare(strict_types=1);

use App\Enums\VideoProvider;
use App\Events\VideoGenerated;
use App\Exceptions\VideoGeneratingException;
use App\Jobs\CheckVideoCreation;
use App\Models\Prompt;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseHas;

test('проверка видео сохраняет результат выбранного провайдера', function (bool $alternative): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);

    Http::fake([
        '*tasks/video-1' => Http::response(['id' => 'video-1', 'status' => 'SUCCEEDED', 'output' => ['https://cdn.example.com/video.mp4']]),
        '*query/video_generation/*' => Http::response(['file_id' => 'file-1']),
        '*files/retrieve*' => Http::response(['file' => ['download_url' => 'https://cdn.example.com/video.mp4']]),
    ]);

    app()->call([new CheckVideoCreation($prompt, $alternative), 'handle']);

    expect($prompt->refresh()->video_url)->toBe('https://cdn.example.com/video.mp4');
    Event::assertDispatched(VideoGenerated::class, fn(VideoGenerated $event) => $event
        ->getPrompt()
        ->is($prompt));
})->with([false, true]);

test('неготовое видео возвращается в очередь', function (): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);

    Http::fake(['*query/video_generation/*' => Http::response([])]);

    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertReleased(60);
    expect($prompt->refresh()->video_url)->toBeNull();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('постоянная ошибка провайдера завершает проверку без готового видео', function (): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);

    Http::fake(['*query/video_generation/*' => Http::response([], 400)]);

    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertFailedWith(RequestException::class);
    Event::assertNotDispatched(VideoGenerated::class);
});

test('временная ошибка Hailuo повторяет только опрос', function (): void {
    Event::fake([VideoGenerated::class]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);
    Http::fake(['*query/video_generation/*' => Http::response([], 503)]);
    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertReleased(60);
    Http::assertSentCount(1);
    Event::assertNotDispatched(VideoGenerated::class);
});

test('самостоятельный ролик отклоняет отсутствующий встроенный звук', function (): void {
    Event::fake([VideoGenerated::class]);
    config(['services.seedance.api_key' => 'test']);
    $prompt = Prompt::factory()->create(['video_id' => 'silent-1', 'video_provider' => VideoProvider::SEEDANCE]);
    Http::fake(['*contents/generations/tasks/silent-1' => Http::response([
        'status' => 'succeeded', 'generate_audio' => false,
        'content' => ['video_url' => 'https://cdn.example.com/silent.mp4'],
    ])]);
    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertFailedWith(VideoGeneratingException::class);
    expect($prompt->refresh()->video_url)->toBeNull();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('повторная проверка готового видео не обращается к провайдеру', function (): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1', 'video_url' => 'https://cdn.example.com/video.mp4']);

    Http::fake();

    app()->call([new CheckVideoCreation($prompt), 'handle']);

    Http::assertNothingSent();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('Seedance опрашивается по сохранённому провайдеру после смены настроек', function (): void {
    Event::fake([VideoGenerated::class]);
    config(['services.video.provider' => 'hailuo', 'services.seedance.api_key' => 'test']);
    Http::fake(['*contents/generations/tasks/video-1' => Http::response([
        'status' => 'succeeded', 'generate_audio' => true,
        'content' => ['video_url' => 'https://cdn.example.com/native.mp4'], 'usage' => ['completion_tokens' => 324900], 'duration' => 15, 'resolution' => '720p',
    ])]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1', 'video_provider' => VideoProvider::SEEDANCE]);
    $job = new CheckVideoCreation($prompt, true, 61);

    app()->call($job->handle(...));

    assertDatabaseHas('generation_provider_usage', ['prompt_id' => $prompt->id, 'provider' => 'seedance', 'completion_tokens' => 324900, 'resolution' => '720p', 'duration' => 15, 'charge_e8' => null]);
    expect($prompt->refresh()->video_url)->toBe('https://cdn.example.com/native.mp4');
    expect($prompt->audio_url)->toBe('https://cdn.example.com/native.mp4');
    expect($job->tries)->toBe(61);
    Http::assertSentCount(1);
    Event::assertDispatched(VideoGenerated::class);
});

test('временный отказ polling Seedance сохраняет задачу для повторного опроса', function (int $status): void {
    Event::fake([VideoGenerated::class]);
    config(['services.seedance.api_key' => 'test']);
    Http::fake(['*contents/generations/tasks/video-1' => Http::response([], $status)]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1', 'video_provider' => VideoProvider::SEEDANCE]);
    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertReleased(60);
    $job->assertNotFailed();

    expect($prompt->refresh()->video_id)->toBe('video-1');
    expect($prompt->video_url)->toBeNull();
    Http::assertSentCount(1);
    Event::assertNotDispatched(VideoGenerated::class);
})->with([429, 503]);

test('таймаут polling Seedance повторяет только чтение статуса', function (): void {
    Event::fake([VideoGenerated::class]);
    config(['services.seedance.api_key' => 'test']);
    Http::fake(['*contents/generations/tasks/video-1' => Http::failedConnection()]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1', 'video_provider' => VideoProvider::SEEDANCE]);
    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertReleased(60);
    $job->assertNotFailed();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('окончательный отказ Seedance завершает job без сохранения видео', function (): void {
    Event::fake([VideoGenerated::class]);
    config(['services.seedance.api_key' => 'test']);
    Http::fake(['*contents/generations/tasks/video-1' => Http::response(['status' => 'failed'])]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1', 'video_provider' => VideoProvider::SEEDANCE]);
    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertFailedWith(VideoGeneratingException::class);
    assertDatabaseHas('generation_provider_usage', ['external_id' => 'video-1', 'status' => 'failed', 'charge_e8' => null]);
    $job->assertNotReleased();

    expect($prompt->refresh()->video_url)->toBeNull();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('готовое немое видео не затирает ранее полученный отдельный звук', function (): void {
    Event::fake([VideoGenerated::class]);
    Http::fake([
        '*query/video_generation/*' => Http::response(['file_id' => 'file-1']),
        '*files/retrieve*' => Http::response(['file' => ['download_url' => 'video.mp4']]),
    ]);
    $prompt = Prompt::factory()->create(['video_id' => 'task-1', 'audio_url' => 'music.mp3']);

    app()->call([new CheckVideoCreation($prompt), 'handle']);

    expect($prompt->refresh()->video_url)->toBe('video.mp4');
    expect($prompt->audio_url)->toBe('music.mp3');
    Event::assertDispatched(VideoGenerated::class);
});

test('сериализованная старая задача сохраняет резервный драйвер и стандартный лимит', function (): void {
    Event::fake([VideoGenerated::class]);
    Http::fake(['*tasks/video-legacy' => Http::response([
        'id' => 'video-legacy', 'status' => 'SUCCEEDED', 'output' => ['legacy.mp4'],
    ])]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-legacy']);
    $restored = unserialize(serialize(new CheckVideoCreation($prompt, true)));
    assert($restored instanceof CheckVideoCreation);

    app()->call($restored->handle(...));

    expect($restored->tries)->toBe(10);
    expect($prompt->refresh()->video_url)->toBe('legacy.mp4');
    Http::assertSentCount(1);
    Event::assertDispatched(VideoGenerated::class);
});
