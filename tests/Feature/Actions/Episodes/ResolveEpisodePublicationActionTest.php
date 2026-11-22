<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Actions\Episodes\ResolveEpisodePublicationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Enums\ChannelType;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Jobs\PublishEpisodeJob;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Tests\Support\acceptedEpisodePublication;
use function Tests\Support\fakeGoogleClient;

test('подтверждает Instagram через чтение owner без повторной публикации', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);
    Http::fake(['graph.instagram.com/123*' => Http::response([
        'id' => '123', 'owner' => ['id' => 'ig-episode'], 'media_type' => 'VIDEO',
        'permalink' => 'https://www.instagram.com/reel/confirmed/',
    ])]);
    $action = app(ResolveEpisodePublicationAction::class);

    $action->run($request, $user, '123');
    $action->run($request, $user, '123');

    expect($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED);
    expect(Post::query()->count())->toBe(1);
    expect(Post::query()->firstOrFail()->external_id)->toBe('123');
    expect(Post::query()->firstOrFail()->episode_assembly_id)->toBe($assembly->id);
    Http::assertSentCount(1);
    Http::assertSent(fn(Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/123'));
    Event::assertDispatchedTimes(PostCreated::class, 1);
});

test('не связывает с запросом чужое Instagram видео', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);
    Http::fake(['graph.instagram.com/123*' => Http::response([
        'id' => '123', 'owner' => ['id' => 'someone-else'], 'media_type' => 'VIDEO',
        'permalink' => 'https://www.instagram.com/reel/foreign/',
    ])]);

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, '123'))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Event::assertNotDispatched(PostCreated::class);
});

test('YouTube подтверждается только для авторизованного канала', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $channel->update(['type' => ChannelType::YOUTUBE, 'credentials' => [
        'access_token' => 'test-token', 'expires_in' => 3600, 'created' => now()->getTimestamp(),
    ]]);
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);

    $handler = fakeGoogleClient(
        new Response(200, ['content-type' => 'application/json'], '{"items":[{"id":"owned-channel"}]}'),
        new Response(200, ['content-type' => 'application/json'], '{"items":[{"id":"video-id","snippet":{"channelId":"owned-channel"}}]}'),
    );

    app(ResolveEpisodePublicationAction::class)->run($request, $user, 'https://www.youtube.com/watch?v=video-id');
    app(ResolveEpisodePublicationAction::class)->run($request, $user, 'https://youtu.be/video-id');

    expect($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED);
    expect(Post::query()->firstOrFail()->url)->toBe('https://www.youtube.com/shorts/video-id');
    expect($handler->count())->toBe(0);
    expect($handler->getLastRequest()?->getMethod())->toBe('GET');
    Event::assertDispatchedTimes(PostCreated::class, 1);
});

test('недостаток YouTube read scope сохраняет неизвестный исход', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $channel->update(['type' => ChannelType::YOUTUBE, 'credentials' => [
        'access_token' => 'upload-only-token', 'expires_in' => 3600, 'created' => now()->getTimestamp(),
    ]]);
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);
    fakeGoogleClient(new Response(403, ['content-type' => 'application/json'], '{"error":{"code":403,"message":"Insufficient authentication scopes"}}'));

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, 'video-id'))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Event::assertNotDispatched(PostCreated::class);
});

test('не выполняет внешний lookup для обычного незапущенного запроса', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    Http::fake();

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, '123'))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::PLANNED);
    Http::assertNothingSent();
});

test('проверяет scope канала до обращения к провайдеру', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);

    $channel->projects()->sync([Project::factory()->create()->id]);
    Http::fake();

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, '123'))
        ->toThrow(ValidationException::class);

    expect(Post::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('повторно проверяет права после медленного lookup', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);
    Http::fake(['graph.instagram.com/123*' => function () use ($user) {
        $user->forceFill(['email_verified_at' => null])->save();

        return Http::response(['id' => '123', 'owner' => ['id' => 'ig-episode'], 'media_type' => 'VIDEO',
            'permalink' => 'https://www.instagram.com/reel/confirmed/']);
    }]);

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, '123'))
        ->toThrow(AuthorizationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Event::assertNotDispatched(PostCreated::class);
});

test('YouTube не подтверждает видео другого канала', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $channel->update(['type' => ChannelType::YOUTUBE, 'credentials' => [
        'access_token' => 'test-token', 'expires_in' => 3600, 'created' => now()->getTimestamp(),
    ]]);
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);
    fakeGoogleClient(
        new Response(200, ['content-type' => 'application/json'], '{"items":[{"id":"owned-channel"}]}'),
        new Response(200, ['content-type' => 'application/json'], '{"items":[{"id":"video-id","snippet":{"channelId":"foreign-channel"}}]}'),
    );

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, 'video-id'))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(0);
    Event::assertNotDispatched(PostCreated::class);
});

test('внешний ID другой публикации не привязывается повторно', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);
    Post::factory()->for($channel)->create(['external_id' => '123']);
    Http::fake();

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $user, '123'))
        ->toThrow(ValidationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->count())->toBe(1);
    Http::assertNothingSent();
});

test('посторонний пользователь не проверяет приватный аккаунт провайдера', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    Event::fake([PostCreated::class]);
    Http::fake();
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);

    $reader = User::factory()->create();

    expect(fn() => app(ResolveEpisodePublicationAction::class)->run($request, $reader, '123'))
        ->toThrow(AuthorizationException::class);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->exists())->toBeFalse();
    Http::assertNothingSent();
    Event::assertNotDispatched(PostCreated::class);
});

test('сверяет ссылку TikTok один раз и принимает повтор по ID', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake();
    $request = app(RequestEpisodePublicationAction::class)
        ->run($assembly, new PublishEpisodeData([$channel->id]), $user)
        ->firstOrFail();
    $request->update(['status' => PublicationStatus::SUBMISSION_UNKNOWN]);

    $channel->update(['type' => ChannelType::TIKTOK, 'external_account' => null, 'credentials' => [
        'channel_id' => 'owned', 'access_token' => 'fake', 'scope' => 'video.list',
        'expires_at' => now()->addDay()->toIso8601String(),
    ]]);
    Http::preventStrayRequests();
    Http::fake(['*/video/query/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['videos' => [['id' => '123']]]])]);
    Event::fake([PostCreated::class]);
    $action = app(ResolveEpisodePublicationAction::class);

    $action->run($request, $user, 'https://www.tiktok.com/@account/video/123');
    $action->run($request, $user, '123');

    expect($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED);
    expect(Post::query()->sole()->url)->toBe('https://www.tiktok.com/player/v1/123');
    Http::assertSentCount(1);
    Event::assertDispatchedTimes(PostCreated::class, 1);
});
