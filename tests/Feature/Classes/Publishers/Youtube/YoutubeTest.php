<?php

declare(strict_types=1);

use App\Classes\Publishers\PublisherFactory;
use App\Enums\ChannelType;
use App\Events\PostCreated;
use App\Models\Channel;
use App\Models\Prompt;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Tests\Support\fakeGoogleClient;

test('выключенный проект не отправляет видео в YouTube', function (): void {
    $prompt = Prompt::factory()->create();
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $channel->project?->update(['is_active' => false]);
    $channel->projects()->syncWithoutDetaching([$prompt->project_id]);
    $handler = fakeGoogleClient(new Response(200));

    expect(app(PublisherFactory::class)->make($channel, $prompt)->publish())->toBeFalse();

    assertDatabaseCount('posts', 0);
    expect($handler->count())->toBe(1);
});

test('YouTube загружает файл и сохраняет публикацию', /** @throws Exception */ function (): void {
    Storage::fake('public');
    Event::fake([PostCreated::class]);

    Storage::disk('public')->put('clip.mp4', 'test-video-bytes');

    $prompt = Prompt::factory()->create();

    $prompt
        ->addMedia(Storage::disk('public')->path('clip.mp4'))
        ->toMediaCollection();

    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'credentials' => [
        'access_token' => 'test-access', 'expires_in' => 3600, 'created' => Carbon::now()->getTimestamp(),
    ]]);
    $channel->projects()->syncWithoutDetaching([$prompt->project_id]);
    $handler = fakeGoogleClient(
        new Response(200, ['location' => 'https://upload.example.com/session']),
        new Response(200, ['content-type' => 'application/json'], '{"id":"uploaded-video"}'),
    );

    expect(app(PublisherFactory::class)
        ->make($channel, $prompt)
        ->publish())->toBeTrue();

    assertDatabaseHas('posts', ['channel_id' => $channel->id, 'prompt_id' => $prompt->id,
        'external_id' => 'uploaded-video', 'url' => 'https://www.youtube.com/shorts/uploaded-video']);

    Event::assertDispatched(PostCreated::class);
    expect($handler->count())->toBe(0);
});

test('YouTube не создаёт публикацию без медиафайла', /** @throws Exception */ function (): void {
    Event::fake([PostCreated::class]);

    $prompt = Prompt::factory()->create();
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'credentials' => [
        'access_token' => 'test-access', 'expires_in' => 3600, 'created' => Carbon::now()->getTimestamp(),
    ]]);
    $channel->projects()->syncWithoutDetaching([$prompt->project_id]);
    $handler = fakeGoogleClient(new Response(200));

    expect(app(PublisherFactory::class)
        ->make($channel, $prompt)
        ->publish())->toBeFalse();

    assertDatabaseCount('posts', 0);

    Event::assertNotDispatched(PostCreated::class);
    expect($handler->count())->toBe(1);
});
