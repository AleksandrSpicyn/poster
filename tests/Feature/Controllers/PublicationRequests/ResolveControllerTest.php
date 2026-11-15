<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Jobs\PublishEpisodeJob;
use App\Models\Post;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Tests\Support\acceptedEpisodePublication;

/** @return array{publication: PublicationRequest, user: User, params: array{series: int, episode: int, revision: int, assembly: int, publication: int}} */
function publicationResolutionContext(): array
{
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $publication = app(RequestEpisodePublicationAction::class)
        ->run($assembly, new PublishEpisodeData([$channel->id]), $user)
        ->firstOrFail();
    $publication->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);

    $revision = $assembly->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();

    return ['publication' => $publication, 'user' => $user, 'params' => [
        'series' => $episode->series_id, 'episode' => $episode->id, 'revision' => $revision->id,
        'assembly' => $assembly->id, 'publication' => $publication->id,
    ]];
}

test('подтверждает receipt через чтение внешнего API и возвращает страницу эпизода', function (): void {
    Storage::fake('local');
    Queue::fake([PublishEpisodeJob::class]);
    $context = publicationResolutionContext();
    Event::fake([PostCreated::class]);
    Http::fake(['graph.instagram.com/123*' => Http::response([
        'id' => '123', 'owner' => ['id' => 'ig-episode'], 'media_type' => 'VIDEO',
        'permalink' => 'https://www.instagram.com/reel/confirmed/',
    ])]);

    actingAs($context['user'])
        ->post(route('episodes.publications.resolve', $context['params']), ['external_id' => '123'])
        ->assertRedirect(route('episodes.show', ['series' => $context['params']['series'], 'episode' => $context['params']['episode']]));

    expect($context['publication']->refresh()->status)->toBe(PublicationStatus::PUBLISHED);
    expect(Post::query()->firstOrFail()->publication_request_id)->toBe($context['publication']->id);
    Http::assertSentCount(1);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET');
    Event::assertDispatchedTimes(PostCreated::class, 1);
});

test('гость не подтверждает публикацию', function (): void {
    Storage::fake('local');
    Queue::fake([PublishEpisodeJob::class]);
    $context = publicationResolutionContext();
    Http::fake();

    postJson(route('episodes.publications.resolve', $context['params']), ['external_id' => '123'])
        ->assertUnauthorized();

    expect($context['publication']->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('неподтвержденный email блокирует проверку внешнего ID', function (): void {
    Storage::fake('local');
    Queue::fake([PublishEpisodeJob::class]);
    $context = publicationResolutionContext();
    $context['user']->forceFill(['email_verified_at' => null])->save();
    Http::fake();

    actingAs($context['user'])
        ->postJson(route('episodes.publications.resolve', $context['params']), ['external_id' => '123'])
        ->assertForbidden();

    expect(Post::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('не принимает URL вместо внешнего ID', function (): void {
    Storage::fake('local');
    Queue::fake([PublishEpisodeJob::class]);
    $context = publicationResolutionContext();
    Http::fake();

    actingAs($context['user'])
        ->postJson(route('episodes.publications.resolve', $context['params']), ['external_id' => 'https://www.instagram.com/reel/example/'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['external_id']);

    expect($context['publication']->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    Http::assertNothingSent();
});

test('проверяет всю цепочку родителей публикации', function (string $parameter): void {
    Storage::fake('local');
    Queue::fake([PublishEpisodeJob::class]);
    $context = publicationResolutionContext();
    $other = publicationResolutionContext();
    $params = $context['params'];
    $params[$parameter] = $other['params'][$parameter];
    Http::fake();

    actingAs($context['user'])
        ->postJson(route('episodes.publications.resolve', $params), ['external_id' => '123'])
        ->assertNotFound();

    expect($context['publication']->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['series', 'episode', 'revision', 'assembly', 'publication']);

test('чужой внешний аккаунт не подтверждает неизвестный receipt', function (): void {
    Storage::fake('local');
    Queue::fake([PublishEpisodeJob::class]);
    $context = publicationResolutionContext();
    Event::fake([PostCreated::class]);
    Http::fake(['graph.instagram.com/123*' => Http::response([
        'id' => '123', 'owner' => ['id' => 'foreign-account'], 'media_type' => 'VIDEO',
        'permalink' => 'https://www.instagram.com/reel/foreign/',
    ])]);

    actingAs($context['user'])
        ->postJson(route('episodes.publications.resolve', $context['params']), ['external_id' => '123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['external_id']);

    expect($context['publication']->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Event::assertNotDispatched(PostCreated::class);
});
