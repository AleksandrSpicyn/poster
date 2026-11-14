<?php

use App\Classes\Publishers\Instagram\Instagram;
use App\Enums\ChannelType;
use App\Events\PostCreated;
use App\Jobs\PublishInstagramJob;
use App\Models\Channel;
use App\Models\Prompt;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->channel = Channel::factory()->create([
        'type' => ChannelType::INSTAGRAM,
        'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1'],
    ]);
    $this->prompt = Prompt::factory()->create();
});

test('публикация Instagram сначала создаёт контейнер и откладывает отправку', function (): void {
    Bus::fake();
    Http::fake(['*/ig-1/media' => Http::response(['id' => 'container-1'])]);
    expect((new Instagram($this->channel, $this->prompt))->publish())->toBeTrue();
    Bus::assertDispatched(PublishInstagramJob::class, fn(PublishInstagramJob $job) => $job->delay->isFuture());
    Http::assertSent(fn(Request $request) => $request['media_type'] === 'REELS' && $request->hasHeader('Authorization', 'Bearer test-token'));
    $this->assertDatabaseCount('posts', 0);
});

test('готовый контейнер создаёт запись публикации и событие', function (): void {
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/ig-1/media_publish' => Http::response(['id' => 'media-1']),
        '*/media-1*' => Http::response(['permalink' => 'https://instagram.com/p/media-1']),
    ]);
    $publisher = new Instagram($this->channel, $this->prompt);
    (new PublishInstagramJob($publisher, 'container-1'))->handle();
    $this->assertDatabaseHas('posts', [
        'channel_id' => $this->channel->id, 'prompt_id' => $this->prompt->id,
        'external_id' => 'media-1', 'url' => 'https://instagram.com/p/media-1',
    ]);
    Event::assertDispatched(PostCreated::class);
    Http::assertSent(fn(Request $request) => $request['creation_id'] === 'container-1');
});

test('ошибка создания контейнера не ставит публикацию в очередь', function (): void {
    Bus::fake();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid media']], 400)]);
    expect((new Instagram($this->channel, $this->prompt))->publish())->toBeFalse();
    Bus::assertNotDispatched(PublishInstagramJob::class);
    Http::assertSentCount(1);
    $this->assertDatabaseCount('posts', 0);
});

test('отказ публикации контейнера не создаёт пост', function (): void {
    Event::fake([PostCreated::class]);
    Http::fake(['*' => Http::response([], 400)]);
    expect(fn() => (new Instagram($this->channel, $this->prompt))->publishContainer('container-1'))->toThrow(RuntimeException::class);
    $this->assertDatabaseCount('posts', 0);
    Event::assertNotDispatched(PostCreated::class);
});
