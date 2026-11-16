<?php

declare(strict_types=1);

use App\Classes\Publishers\AbstractPublisher;
use App\Classes\Publishers\Instagram\Instagram;
use App\Enums\ChannelType;
use App\Events\PostCreated;
use App\Jobs\PublishInstagramJob;
use App\Models\Channel;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

test('новый payload восстанавливает публикацию без сериализации зависимостей', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::INSTAGRAM, 'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1']]);
    $prompt = Prompt::factory()->create();
    $publisher = app()->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt]);
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/ig-1/media_publish' => Http::response(['id' => 'media-1']),
        '*/media-1*' => Http::response(['permalink' => 'https://instagram.com/p/media-1']),
    ]);
    $payload = serialize(new PublishInstagramJob($publisher, 'container-1'));
    $job = unserialize($payload);

    if (! $job instanceof PublishInstagramJob) {
        throw new RuntimeException('Invalid job fixture.');
    }

    app()->call($job->handle(...));

    expect($payload)->not->toContain(Instagram::class);
    assertDatabaseHas('posts', ['channel_id' => $channel->id, 'prompt_id' => $prompt->id, 'external_id' => 'media-1']);
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/media_publish') && $request['creation_id'] === 'container-1');
    Event::assertDispatched(PostCreated::class);
});

test('исторический payload публикует контейнер с восстановленными зависимостями', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::INSTAGRAM, 'credentials' => ['access_token' => 'test-token', 'channel_id' => 'ig-1']]);
    $prompt = Prompt::factory()->create();
    $job = legacyInstagramJob($channel, $prompt);
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/ig-1/media_publish' => Http::response(['id' => 'legacy-media']),
        '*/legacy-media*' => Http::response(['permalink' => 'https://instagram.com/p/legacy-media']),
    ]);

    app()->call($job->handle(...));

    assertDatabaseHas('posts', ['channel_id' => $channel->id, 'prompt_id' => $prompt->id, 'external_id' => 'legacy-media']);
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/media_publish') && $request['creation_id'] === 'legacy-container');
    Event::assertDispatched(PostCreated::class);
});

test('исторический payload не отправляет контейнер выключенного проекта', function (): void {
    $channel = Channel::factory()->for(Project::factory()->state(['is_active' => false]))->create(['type' => ChannelType::INSTAGRAM]);
    $prompt = Prompt::factory()->create();
    $job = legacyInstagramJob($channel, $prompt);
    Http::fake();

    expect(fn() => app()->call($job->handle(...)))->toThrow(RuntimeException::class, 'The project is disabled.');

    assertDatabaseCount('posts', 0);
    Http::assertNothingSent();
});

test('смена типа канала останавливает отложенную публикацию', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $prompt = Prompt::factory()->create();
    $publisher = app()->makeWith(Instagram::class, ['channel' => $channel, 'prompt' => $prompt]);
    $job = new PublishInstagramJob($publisher, 'container-1');
    Http::fake();

    expect(fn() => app()->call($job->handle(...)))->toThrow(RuntimeException::class, 'The queued publication requires a staged publisher.');

    assertDatabaseCount('posts', 0);
    Http::assertNothingSent();
});

/** В старом payload отсутствуют новые зависимости Publisher и новые поля job. */
function legacyInstagramJob(Channel $channel, Prompt $prompt): PublishInstagramJob
{
    $publisher = new ReflectionClass(Instagram::class)->newInstanceWithoutConstructor();
    new ReflectionProperty(AbstractPublisher::class, 'channel')->setValue($publisher, $channel);
    new ReflectionProperty(AbstractPublisher::class, 'prompt')->setValue($publisher, $prompt);
    $values = [
        "\0" . PublishInstagramJob::class . "\0publisher" => $publisher,
        "\0" . PublishInstagramJob::class . "\0containerId" => 'legacy-container',
    ];
    $payload = 'O:' . strlen(PublishInstagramJob::class) . ':"' . PublishInstagramJob::class . '"' . substr(serialize($values), 1);
    $restored = unserialize($payload);

    if (! $restored instanceof PublishInstagramJob) {
        throw new RuntimeException('Invalid legacy job fixture.');
    }

    return $restored;
}
