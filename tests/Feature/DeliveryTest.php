<?php

use App\Classes\Generators\VideoGenerator\AlternativeVideoGenerator;
use App\Classes\Generators\VideoGenerator\VideoGenerator;
use App\Classes\Publishers\PublisherFactory;
use App\Enums\ChannelType;
use App\Events\AudioGenerated;
use App\Events\PostCreated;
use App\Events\VideoGenerated;
use App\Events\VideoGenerating;
use App\Jobs\CheckVideoCreation;
use App\Jobs\SaveVideoJob;
use App\Listeners\PostCreatedListener;
use App\Models\Channel;
use App\Models\Chat;
use App\Models\Prompt;
use App\Notifications\PostCreatedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

// Fake only the external video boundary; persistence and job orchestration stay real.
test('проверка видео сохраняет результат выбранного провайдера', function (bool $alternative): void {
    Event::fake([VideoGenerated::class]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);
    $this->mock($alternative ? AlternativeVideoGenerator::class : VideoGenerator::class)
        ->shouldReceive('check')->once()->with('video-1')->andReturn('https://cdn.example.com/video.mp4');
    (new CheckVideoCreation($prompt, $alternative))->handle();
    expect($prompt->fresh()->video_url)->toBe('https://cdn.example.com/video.mp4');
    Event::assertDispatched(VideoGenerated::class, fn(VideoGenerated $event) => $event->getPrompt()->is($prompt));
})->with([false, true]);

test('неготовое видео возвращается в очередь', function (): void {
    Event::fake([VideoGenerated::class]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);
    $this->mock(VideoGenerator::class)->shouldReceive('check')->with('video-1')->once()->andReturnNull();
    $job = (new CheckVideoCreation($prompt))->withFakeQueueInteractions();
    $job->handle();
    $job->assertReleased(60);
    expect($prompt->fresh()->video_url)->toBeNull();
    Event::assertNotDispatched(VideoGenerated::class);
});

test('ошибка провайдера завершает проверку без готового видео', function (): void {
    Event::fake([VideoGenerated::class]);
    $prompt = Prompt::factory()->create(['video_id' => 'video-1']);
    $this->mock(VideoGenerator::class)->shouldReceive('check')->once()->andThrow(new RuntimeException('provider failure'));
    $job = (new CheckVideoCreation($prompt))->withFakeQueueInteractions();
    $job->handle();
    $job->assertFailedWith(RuntimeException::class);
    Event::assertNotDispatched(VideoGenerated::class);
});

test('события генерации направляются в соответствующие очереди', function (): void {
    Bus::fake();
    $prompt = Prompt::factory()->create();
    event(new VideoGenerating($prompt, true));
    event(new AudioGenerated($prompt));
    event(new VideoGenerated($prompt));
    Bus::assertDispatched(CheckVideoCreation::class);
    Bus::assertDispatchedTimes(SaveVideoJob::class, 2);
});

test('уведомление о публикации направляется всем сохранённым чатам', function (): void {
    Notification::fake();
    $post = \App\Models\Post::factory()->create();
    $chats = collect(['chat-1', 'chat-2'])->map(fn(string $id) => Chat::create(['chat_id' => $id]));
    (new PostCreatedListener())->handle(new PostCreated($post));
    Notification::assertSentTo($chats, PostCreatedNotification::class);
    expect((new PostCreatedNotification($post))->toTelegram($chats->first())->toArray()['text'])->toContain($post->url);
});

test('незавершённые интеграции не создают публикаций', function (ChannelType $type): void {
    $channel = Channel::factory()->create(['type' => $type]);
    $prompt = Prompt::factory()->create();
    expect(PublisherFactory::make($channel, $prompt)->publish())->toBeFalse();
    $this->assertDatabaseCount('posts', 0);
})->with([ChannelType::TIKTOK, ChannelType::VK]);
