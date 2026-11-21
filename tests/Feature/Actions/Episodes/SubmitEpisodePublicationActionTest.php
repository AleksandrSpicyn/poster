<?php

declare(strict_types=1);

use App\Actions\Episodes\FinishEpisodePublicationAction;
use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Actions\Publications\ReconcilePublicationsAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Events\WorkspaceUpdated;
use App\Jobs\FinishEpisodePublicationJob;
use App\Jobs\PublishEpisodeJob;
use App\Models\Post;
use App\Models\PublicationRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Tests\Support\acceptedEpisodePublication;

test('выключение проекта блокирует ожидающую публикацию', function (bool $container): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class, FinishEpisodePublicationJob::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->sole();
    $request->update(['scheduled_at' => now()]);

    if ($container) {
        $request->update(['status' => PublicationStatus::PROCESSING, 'container_id' => 'project-disabled-container']);
    }

    $channel->project?->update(['is_active' => false]);

    if ($container) {
        app(FinishEpisodePublicationAction::class)->run($request);
    } else {
        app(SubmitEpisodePublicationAction::class)->run($request);
    }

    expect($request->refresh()->status)->toBe(PublicationStatus::FAILED);
    expect($request->error_message)->toBe('The project is disabled.');
    expect(Post::query()->exists())->toBeFalse();
    Http::assertNothingSent();
})->with(['отправка' => false, 'готовый контейнер' => true]);

test('публикует принятую сборку двумя этапами и сохраняет точную версию', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class, FinishEpisodePublicationJob::class]);
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/ig-episode/media' => Http::response(['id' => 'container-episode']),
        '*/ig-episode/media_publish' => Http::response(['id' => 'media-episode']),
        '*/media-episode*' => Http::response(['permalink' => 'https://instagram.com/p/media-episode']),
    ]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['scheduled_at' => now()]);

    app(SubmitEpisodePublicationAction::class)->run($request);
    app(SubmitEpisodePublicationAction::class)->run($request);

    expect($request->refresh()->status)->toBe(PublicationStatus::PROCESSING);
    expect($request->container_id)->toBe('container-episode');
    Queue::assertPushed(FinishEpisodePublicationJob::class, 1);
    app(FinishEpisodePublicationAction::class)->run($request);
    app(FinishEpisodePublicationAction::class)->run($request);

    $post = Post::query()->firstOrFail();
    expect($post->episode_assembly_id)->toBe($assembly->id);
    expect($post->publication_request_id)->toBe($request->id);
    expect($post->prompt_id)->toBeNull();
    expect($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED);
    expect(Post::query()->count())->toBe(1);
    Event::assertDispatchedTimes(PostCreated::class, 1);
    Http::assertSentCount(3);
    expect($post->title)->toBe('The locked door');
    expect($post->description)->toStartWith("Who has the key?\n\n");
    Http::assertSent(fn(Request $outgoing): bool => ($outgoing['caption'] ?? null) === $post->title . "\n\n" . $post->description);
});

test('неизвестная отправка не повторяется при повторе job', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class, FinishEpisodePublicationJob::class]);
    Http::fake(['*/ig-episode/media' => Http::response([], 503)]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['scheduled_at' => now()]);
    Event::fake([WorkspaceUpdated::class]);

    app(SubmitEpisodePublicationAction::class)->run($request);
    app(SubmitEpisodePublicationAction::class)->run($request);

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    expect(Post::query()->exists())->toBeFalse();
    Http::assertSentCount(1);
    Queue::assertNotPushed(FinishEpisodePublicationJob::class);
    Event::assertDispatchedTimes(WorkspaceUpdated::class, 2);
});

test('reconciler восстанавливает доставку только безопасных запросов', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class, FinishEpisodePublicationJob::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['scheduled_at' => now()]);
    $request->update(['status' => PublicationStatus::SUBMITTING, 'submitted_at' => now()->subMinutes(11)]);
    Queue::fake([PublishEpisodeJob::class, FinishEpisodePublicationJob::class]);

    app(ReconcilePublicationsAction::class)->run();

    expect(PublicationRequest::query()->findOrFail($request->id)->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    Queue::assertNothingPushed();
});

test('отзыв доступа блокирует отправку и публикацию готового контейнера', function (bool $container): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class, FinishEpisodePublicationJob::class]);
    Event::fake([PostCreated::class]);
    Http::fake();
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->firstOrFail();
    $request->update(['scheduled_at' => now()]);

    if ($container) {
        $request->update(['status' => PublicationStatus::PROCESSING, 'container_id' => 'existing-container']);
    }

    $user->forceFill(['email' => 'revoked@example.test'])->save();

    if ($container) {
        app(FinishEpisodePublicationAction::class)->run($request);
    } else {
        app(SubmitEpisodePublicationAction::class)->run($request);
    }

    expect($request->refresh()->status)->toBe(PublicationStatus::FAILED);
    expect(Post::query()->exists())->toBeFalse();
    expect($user->hasVerifiedEmail())->toBeTrue();
    Http::assertNothingSent();
    Event::assertNotDispatched(PostCreated::class);
    Queue::assertNotPushed(FinishEpisodePublicationJob::class);
})->with(['отправка видео' => false, 'публикация контейнера' => true]);

test('изменение общего канала блокирует уже поставленную публикацию', function (bool $disconnect): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake([PublishEpisodeJob::class]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->sole();
    $request->update(['scheduled_at' => now()]);

    if ($disconnect) {
        $channel->update(['credentials' => null]);
    } else {
        $channel->projects()->detach();
    }

    app(SubmitEpisodePublicationAction::class)->run($request);

    expect($request->refresh()->status)->toBe(PublicationStatus::FAILED);
    expect(Post::query()->exists())->toBeFalse();
    Http::assertNothingSent();
})->with([true, false]);

test('TikTok отправляет один черновик и ждёт публикации автором', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $channel->update(['type' => 'tiktok', 'credentials' => ['channel_id' => 'owner', 'access_token' => 'access', 'scope' => 'video.upload,video.list,user.info.basic', 'expires_at' => now()->addHour()->toIso8601String()]]);
    Queue::fake();
    Event::fake([PostCreated::class]);
    Http::fake([
        '*/inbox/video/init/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['publish_id' => 'receipt']]),
        '*/status/fetch/' => Http::sequence()
            ->push(['error' => ['code' => 'ok'], 'data' => ['status' => 'SEND_TO_USER_INBOX']])
            ->push(['error' => ['code' => 'ok'], 'data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [123456]]]),
    ]);
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->sole();
    $request->update(['scheduled_at' => now()]);

    app(SubmitEpisodePublicationAction::class)->run($request);
    app(FinishEpisodePublicationAction::class)->run($request->refresh());

    expect($request->refresh()->status)->toBe(PublicationStatus::PROCESSING)
        ->and($request->container_id)->toBe('receipt')
        ->and(Post::query()->count())->toBe(0);

    app(SubmitEpisodePublicationAction::class)->run($request);
    app(FinishEpisodePublicationAction::class)->run($request->refresh());

    expect(Post::query()->sole()->external_id)->toBe('123456')
        ->and($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED);
    Http::assertSentCount(3);
});
