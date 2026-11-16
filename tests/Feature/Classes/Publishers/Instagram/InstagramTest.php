<?php

declare(strict_types=1);

use App\Classes\Publishers\Instagram\Instagram;
use App\Enums\ChannelType;
use App\Events\PostCreated;
use App\Jobs\PublishInstagramJob;
use App\Models\Channel;
use App\Models\Prompt;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

test('выключение проекта блокирует отправку готового legacy контейнера', function (): void {
    [$channel, $prompt] = instagramModels();
    $publisher = app()->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt]);
    $channel->project?->update(['is_active' => false]);
    Http::fake();

    expect(fn() => app()->call([new PublishInstagramJob($publisher, 'pending-container'), 'handle']))->toThrow(RuntimeException::class);

    assertDatabaseCount('posts', 0);
    Http::assertNothingSent();
});

/** @return array{Channel, Prompt} */
function instagramModels(): array
{
    $channel = Channel::factory()->create([
        'type' => ChannelType::INSTAGRAM,
        'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1'],
    ]);
    $prompt = Prompt::factory()->create();

    return [$channel, $prompt];
}

test('публикация Instagram сначала создаёт контейнер и откладывает отправку', /** @throws Exception */ function (): void {
    [$channel, $prompt] = instagramModels();

    Bus::fake();
    Http::fake(['*/ig-1/media' => Http::response(['id' => 'container-1'])]);

    expect(app()
        ->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt])
        ->publish())->toBeTrue();
    Bus::assertDispatched(PublishInstagramJob::class, fn(PublishInstagramJob $job): bool => $job->delay instanceof CarbonInterface && $job->delay->isFuture());
    Http::assertSent(fn(Request $request): bool => $request['media_type'] === 'REELS' && $request->hasHeader('Authorization', 'Bearer test-token'));

    assertDatabaseCount('posts', 0);
});

test('готовый контейнер создаёт запись публикации и событие', /** @throws Exception */ function (): void {
    [$channel, $prompt] = instagramModels();

    Event::fake([PostCreated::class]);
    Http::fake([
        '*/ig-1/media_publish' => Http::response(['id' => 'media-1']),
        '*/media-1*' => Http::response(['permalink' => 'https://instagram.com/p/media-1']),
    ]);

    $publisher = app()->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt]);

    app()->call([new PublishInstagramJob($publisher, 'container-1'), 'handle']);

    assertDatabaseHas('posts', [
        'channel_id' => $channel->id, 'prompt_id' => $prompt->id,
        'external_id' => 'media-1', 'url' => 'https://instagram.com/p/media-1',
    ]);

    Event::assertDispatched(PostCreated::class);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/media-1') && $request['fields'] === 'permalink');
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/media_publish') && $request['creation_id'] === 'container-1');
});

test('ошибка создания контейнера не ставит публикацию в очередь', /** @throws Exception */ function (): void {
    [$channel, $prompt] = instagramModels();

    Bus::fake();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid media']], 400)]);

    expect(app()
        ->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt])
        ->publish())->toBeFalse();
    Bus::assertNotDispatched(PublishInstagramJob::class);
    Http::assertSentCount(1);

    assertDatabaseCount('posts', 0);
});

test('отказ публикации контейнера не создаёт пост', function (): void {
    [$channel, $prompt] = instagramModels();

    Event::fake([PostCreated::class]);
    Http::fake(['*' => Http::response([], 400)]);

    expect(/** @throws Exception */ fn() => app()
        ->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt])
        ->publishContainer('container-1'))->toThrow(RuntimeException::class);

    assertDatabaseCount('posts', 0);

    Event::assertNotDispatched(PostCreated::class);
});
