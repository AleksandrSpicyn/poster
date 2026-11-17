<?php

declare(strict_types=1);

use App\Actions\Episodes\FinishEpisodePublicationAction;
use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Actions\Series\BuildSeriesAssemblyAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Data\Series\AcceptSeriesVideoData;
use App\Enums\ChannelType;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Jobs\FinishEpisodePublicationJob;
use App\Models\Channel;
use App\Models\Post;
use App\Tasks\Publications\ClaimPublication;
use App\Tasks\Publications\CreatePublicationRequest;
use App\Tasks\Publications\CreateSeriesPublication;
use App\Tasks\Series\AcceptSeriesAssembly;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\singleVideoFixture;

test('запрещает отправку промежуточной части даже ручным запросом', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $episode = $context['series']->episodes()->firstOrFail();
    $accepted = $episode->acceptedAssembly()->firstOrFail();

    expect(fn() => app(CreatePublicationRequest::class)->run($accepted, new PublishEpisodeData([$context['channel']->id]), $context['user']))->toThrow(ValidationException::class);
    expect($accepted->publications()->count())->toBe(0);
});

test('повтор публикации закрепляет один итоговый выпуск и первый claim блокирует подмену', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    app(BuildSeriesAssemblyAction::class)->run($assembly->id);
    $assembly->refresh();
    app(AcceptSeriesAssembly::class)->run($assembly, new AcceptSeriesVideoData($assembly->lock_version, true, 'All boundaries checked.'), $context['user']);
    $data = new PublishEpisodeData([$context['channel']->id]);
    $request = app(CreateSeriesPublication::class)->run($assembly, $data, $context['user'])->sole();

    $duplicate = app(CreateSeriesPublication::class)->run($assembly, $data, $context['user'])->sole();
    $claimed = app(ClaimPublication::class)->run($request);

    expect($duplicate->id)->toBe($request->id);
    expect($claimed)->toBeTrue();
    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMITTING);
    expect($context['series']->refresh()->release_started_at)->not->toBeNull();
    expect(fn() => app(SaveSeriesAssembly::class)->run($context['series']))->toThrow(ValidationException::class);
});

test('единый файл публикуется в два канала по одному разу без публикаций частей', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $context['channel']->update(['type' => ChannelType::INSTAGRAM, 'credentials' => ['access_token' => 'test', 'channel_id' => 'final-a']]);
    $second = Channel::factory()->for($context['project'])->create(['type' => ChannelType::INSTAGRAM, 'credentials' => ['access_token' => 'test', 'channel_id' => 'final-b']]);
    $context['run']->update(['input_snapshot' => [...($context['run']->input_snapshot ?? []), 'channel_ids' => [$context['channel']->id, $second->id]]]);
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    app(BuildSeriesAssemblyAction::class)->run($assembly->id);
    $assembly->refresh();
    app(AcceptSeriesAssembly::class)->run($assembly, new AcceptSeriesVideoData($assembly->lock_version, true, 'All seams reviewed.'), $context['user']);
    Queue::fake([FinishEpisodePublicationJob::class]);
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/final-a/media' => Http::response(['id' => 'container-a']),
        '*/final-b/media' => Http::response(['id' => 'container-b']),
        '*/final-a/media_publish' => Http::response(['id' => 'media-a']),
        '*/final-b/media_publish' => Http::response(['id' => 'media-b']),
        '*/media-a*' => Http::response(['permalink' => 'https://instagram.com/p/media-a']),
        '*/media-b*' => Http::response(['permalink' => 'https://instagram.com/p/media-b']),
    ]);
    $requests = app(CreateSeriesPublication::class)->run($assembly, new PublishEpisodeData([$context['channel']->id, $second->id]), $context['user']);

    foreach ($requests as $request) {
        app(SubmitEpisodePublicationAction::class)->run($request);
        app(SubmitEpisodePublicationAction::class)->run($request);
        app(FinishEpisodePublicationAction::class)->run($request);
        app(FinishEpisodePublicationAction::class)->run($request);
    }

    expect(Post::query()->count())->toBe(2);
    expect(Post::query()->where('series_assembly_id', $assembly->id)->count())->toBe(2);
    expect(Post::query()->whereNotNull('episode_assembly_id')->count())->toBe(0);
    expect($requests->map(fn($request) => $request->refresh()->status)->unique()->all())->toBe([PublicationStatus::PUBLISHED]);
    Http::assertSentCount(6);
    Queue::assertPushed(FinishEpisodePublicationJob::class, 2);
    Event::assertDispatchedTimes(PostCreated::class, 2);
});
