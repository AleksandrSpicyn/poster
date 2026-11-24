<?php

declare(strict_types=1);

use App\Actions\Episodes\FinishEpisodePublicationAction;
use App\Actions\Episodes\SubmitEpisodePublicationAction;
use App\Enums\ChannelType;
use App\Enums\PublicationStatus;
use App\Events\PostCreated;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\readyPublicationExport;

/** @return array{Channel, array<string,mixed>} */
function tiktokShortForm(): array
{
    Storage::fake('local');
    Queue::fake();
    Event::fake([PostCreated::class]);
    config(['services.tiktok.direct_post' => true]);
    $owner = User::factory()->create();
    $project = Project::factory()->recycle($owner)->create();
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::TIKTOK, 'credentials' => [
        'channel_id' => 'alpha', 'scope' => 'video.publish,video.upload', 'access_token' => 'test-access',
        'expires_at' => now()->addHour()->toIso8601String(),
    ]]);
    $short = Prompt::factory()->for($project)->create(['generated' => true]);
    $media = $short->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection('default', 'local');
    readyPublicationExport($short, $owner);
    actingAs($owner);

    return [$channel, ['media_id' => $media->id, 'account_id' => 'alpha', 'mode' => 'direct', 'privacy' => 'SELF_ONLY',
        'consent' => true, 'title' => 'Авторский ролик']];
}

test('подтверждение short сохраняет одну квитанцию и проверяет результат', function (string $mode): void {
    [$channel, $fields] = tiktokShortForm();
    Http::fake([
        '*/creator_info/query/' => Http::response(tiktokCreatorResponse()),
        '*/video/init/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['publish_id' => 'receipt']]),
        '*/status/fetch/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => [123]]]),
    ]);

    foreach ([1, 2] as $attempt) {
        post(route('posts.tiktok.store', $channel), [...$fields, 'mode' => $mode])->assertSessionHasNoErrors()->assertRedirect(route('posts'));
    }

    Queue::assertPushed(PublishEpisodeJob::class, 1);
    $request = PublicationRequest::query()->sole();
    app(SubmitEpisodePublicationAction::class)->run($request);
    expect($request->refresh()->container_id)->toBe('receipt');
    app(SubmitEpisodePublicationAction::class)->run($request);
    app(FinishEpisodePublicationAction::class)->run($request->refresh());

    expect($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED)
        ->and(Post::query()->sole()->prompt_id)->toBe($request->prompt_id);
    $prefix = $mode === 'direct' ? '/publish/video/init/' : '/publish/inbox/video/init/';
    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), $prefix)
        && data_get($request->data(), 'source_info.source') === 'PULL_FROM_URL'
        && ($mode === 'inbox' || data_get($request->data(), 'post_info.privacy_level') === 'SELF_ONLY'));
})->with(['direct', 'inbox']);

test('недоступные параметры публикации отклоняются до постановки задания', function (array $change): void {
    [$channel, $fields] = tiktokShortForm();
    Http::fake(['*/creator_info/query/' => Http::response(tiktokCreatorResponse())]);

    post(route('posts.tiktok.store', $channel), [...$fields, ...$change])->assertSessionHasErrors();

    Queue::assertNotPushed(PublishEpisodeJob::class);
    expect(PublicationRequest::query()->count())->toBe(0);
    Http::assertNotSent(fn(Request $request): bool => str_ends_with($request->url(), '/video/init/'));
})->with([[['account_id' => 'foreign']], [['privacy' => 'PUBLIC_TO_EVERYONE']], [['duet' => true]], [['disclose' => true, 'partner' => true]], [['consent' => false]]]);

test('публичный Direct Post передаёт согласованные настройки рекламы', function (): void {
    [$channel, $fields] = tiktokShortForm();
    config(['services.tiktok.public_direct_post' => true]);
    Http::fake(['*/creator_info/query/' => Http::response(tiktokCreatorResponse()),
        '*/video/init/' => Http::response(['error' => ['code' => 'ok'], 'data' => ['publish_id' => 'test']])]);

    post(route('posts.tiktok.store', $channel), [...$fields, 'privacy' => 'PUBLIC_TO_EVERYONE', 'disclose' => true, 'partner' => true, 'comment' => true, 'ai' => true])
        ->assertSessionHasNoErrors();
    app(SubmitEpisodePublicationAction::class)->run(PublicationRequest::query()->sole());

    Http::assertSent(fn(Request $request): bool => str_ends_with($request->url(), '/video/init/')
        && data_get($request->data(), 'post_info.brand_content_toggle') === true
        && data_get($request->data(), 'post_info.is_aigc') === true && data_get($request->data(), 'post_info.disable_comment') === false);
});

test('неопределённый ответ провайдера блокирует повторную отправку', function (): void {
    [$channel, $fields] = tiktokShortForm();
    Http::fake(['*/inbox/video/init/' => Http::response([], 503)]);

    post(route('posts.tiktok.store', $channel), [...$fields, 'mode' => 'inbox'])->assertSessionHasNoErrors();
    $request = PublicationRequest::query()->sole();
    app(SubmitEpisodePublicationAction::class)->run($request);
    app(SubmitEpisodePublicationAction::class)->run($request->refresh());

    expect($request->refresh()->status)->toBe(PublicationStatus::SUBMISSION_UNKNOWN);
    Http::assertSentCount(1);
});

test('потеря доступа после подтверждения блокирует доставку', function (): void {
    [$channel, $fields] = tiktokShortForm();
    post(route('posts.tiktok.store', $channel), [...$fields, 'mode' => 'inbox'])->assertSessionHasNoErrors();
    $channel->projects()->detach();

    $request = PublicationRequest::query()->sole();
    app(SubmitEpisodePublicationAction::class)->run($request);

    expect($request->refresh()->status)->toBe(PublicationStatus::FAILED);
    Http::assertNothingSent();
});

test('сбой проверки перед отправкой не создаёт заявку или задание', function (): void {
    [$channel, $fields] = tiktokShortForm();
    Http::fake(['*/creator_info/query/' => Http::failedConnection()]);

    post(route('posts.tiktok.store', $channel), $fields)
        ->assertSessionHasErrors('mode');

    expect(PublicationRequest::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
