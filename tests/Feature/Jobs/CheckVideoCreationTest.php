<?php

declare(strict_types=1);

use App\Classes\Generators\VideoGenerator\AlternativeVideoGenerator;
use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Events\VideoGenerated;
use App\Jobs\CheckVideoCreation;
use App\Models\Prompt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

test('проверка видео сохраняет результат выбранного провайдера', function (bool $alternative): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);

    mock($alternative ? AlternativeVideoGenerator::class : VideoGenerator::class)
        ->shouldReceive('check')
        ->once()
        ->with('video-1')
        ->andReturn('https://cdn.example.com/video.mp4');

    app()->call([new CheckVideoCreation($prompt, $alternative), 'handle']);

    expect($prompt->refresh()->video_url)->toBe('https://cdn.example.com/video.mp4');
    Event::assertDispatched(VideoGenerated::class, fn(VideoGenerated $event) => $event
        ->getPrompt()
        ->is($prompt));
})->with([false, true]);

test('неготовое видео возвращается в очередь', function (): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);

    mock(VideoGenerator::class)
        ->shouldReceive('check')
        ->with('video-1')
        ->once()
        ->andReturnNull();

    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertReleased(60);
    expect($prompt->refresh()->video_url)->toBeNull();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('ошибка провайдера завершает проверку без готового видео', function (): void {
    Event::fake([VideoGenerated::class]);

    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);

    mock(VideoGenerator::class)
        ->shouldReceive('check')
        ->once()
        ->andThrow(new RuntimeException('provider failure'));

    $job = new CheckVideoCreation($prompt)->withFakeQueueInteractions();

    app()->call($job->handle(...));

    $job->assertFailedWith(RuntimeException::class);
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
