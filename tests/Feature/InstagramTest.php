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

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/** @return array{\App\Models\Channel, \App\Models\Prompt} */
function instagramModels(): array
{
    $channel = Channel::factory()->create([
        'type' => ChannelType::INSTAGRAM,
        'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1'],
    ]);
    $prompt = Prompt::factory()->create();

    return [$channel, $prompt];
}

test('публикация Instagram сначала создаёт контейнер и откладывает отправку', function (): void {
    [$channel, $prompt] = instagramModels();
    Bus::fake();
    Http::fake(['*/ig-1/media' => Http::response(['id' => 'container-1'])]);
    expect((new Instagram($channel, $prompt))->publish())->toBeTrue();
    Bus::assertDispatched(PublishInstagramJob::class, fn(PublishInstagramJob $job) => $job->delay->isFuture());
    Http::assertSent(fn(Request $request) => $request['media_type'] === 'REELS' && $request->hasHeader('Authorization', 'Bearer test-token'));
    assertDatabaseCount('posts', 0);
});

test('готовый контейнер создаёт запись публикации и событие', function (): void {
    [$channel, $prompt] = instagramModels();
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/ig-1/media_publish' => Http::response(['id' => 'media-1']),
        '*/media-1*' => Http::response(['permalink' => 'https://instagram.com/p/media-1']),
    ]);
    $publisher = new Instagram($channel, $prompt);
    (new PublishInstagramJob($publisher, 'container-1'))->handle();
    assertDatabaseHas('posts', [
        'channel_id' => $channel->id, 'prompt_id' => $prompt->id,
        'external_id' => 'media-1', 'url' => 'https://instagram.com/p/media-1',
    ]);
    Event::assertDispatched(PostCreated::class);
    Http::assertSent(fn(Request $request) => str_ends_with($request->url(), '/media_publish') && $request['creation_id'] === 'container-1');
});

test('ошибка создания контейнера не ставит публикацию в очередь', function (): void {
    [$channel, $prompt] = instagramModels();
    Bus::fake();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid media']], 400)]);
    expect((new Instagram($channel, $prompt))->publish())->toBeFalse();
    Bus::assertNotDispatched(PublishInstagramJob::class);
    Http::assertSentCount(1);
    assertDatabaseCount('posts', 0);
});

test('отказ публикации контейнера не создаёт пост', function (): void {
    [$channel, $prompt] = instagramModels();
    Event::fake([PostCreated::class]);
    Http::fake(['*' => Http::response([], 400)]);
    expect(fn() => (new Instagram($channel, $prompt))->publishContainer('container-1'))->toThrow(RuntimeException::class);
    assertDatabaseCount('posts', 0);
    Event::assertNotDispatched(PostCreated::class);
});
