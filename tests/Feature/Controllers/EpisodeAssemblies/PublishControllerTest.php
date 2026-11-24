<?php

declare(strict_types=1);

use App\Actions\Publications\ReconcilePublicationsAction;
use App\Classes\Channels\FacebookOAuth;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\PublicationProviderPhase;
use App\Enums\PublicationStatus;
use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use App\Events\PostCreated;
use App\Jobs\FinishEpisodePublicationJob;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Tasks\Publications\ClaimPublication;
use App\Tasks\Publications\PrepareEpisodePublication;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\acceptedEpisodePublication;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\workspaceOwner;

test('публикация создаёт receipt для выбранного канала без внешней отправки в HTTP', function (): void {
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::ACCEPTED]);
    $context['episode']->update(['accepted_assembly_id' => $assembly->id]);
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['project_id' => $series->project_id, 'type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test-token']]);
    Bus::fake();

    actingAs($context['user'])
        ->post(route('episodes.assemblies.publish', [...$context['params'], 'assembly' => $assembly]), ['channel_ids' => [$channel->id]])
        ->assertRedirect();

    $request = PublicationRequest::query()->firstOrFail();
    expect($request->episode_assembly_id)->toBe($assembly->id);
    expect($request->channel_id)->toBe($channel->id);
    expect($request->payload['description'])->toBe(trim(($context['revision']->public_caption ?? '') . "\n\n" . $series->name . ' · ' . $context['episode']->number));
    expect($request->scheduled_at?->isFuture())->toBeTrue();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('разовая подпись остаётся в первой заявке каждого канала', function (?string $caption): void {
    $context = episodeHttpFixture();
    $revision = $context['revision'];
    $revision->update(['public_caption' => 'Подпись сценария']);

    $episode = $context['episode'];
    $assembly = EpisodeAssembly::factory()->for($revision)->create(['status' => AssemblyStatus::ACCEPTED]);
    $episode->update(['accepted_assembly_id' => $assembly->id]);
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['project_id' => $episode->series()->firstOrFail()->project_id, 'type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test']]);
    $url = route('episodes.assemblies.publish', [...$context['params'], 'assembly' => $assembly]);
    Bus::fake();

    actingAs($context['user'])
        ->postJson($url, ['channel_ids' => [$channel->id], 'caption' => str_repeat('я', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('caption');
    expect(PublicationRequest::query()->count())->toBe(0);

    actingAs($context['user'])
        ->post($url, ['channel_ids' => [$channel->id], 'caption' => $caption])
        ->assertRedirect();
    actingAs($context['user'])
        ->post($url, ['channel_ids' => [$channel->id], 'caption' => 'Другая подпись'])
        ->assertRedirect();

    $request = PublicationRequest::query()->sole();
    expect($request->payload['description'])->toBe($caption ?? '');
    expect($revision->refresh()->public_caption)->toBe('Подпись сценария');
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with(['Только для этой отправки', '', null]);

test('черновик не создаёт публикацию', function (): void {
    $context = episodeHttpFixture();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::READY]);
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    Bus::fake();

    actingAs($context['user'])
        ->postJson(route('episodes.assemblies.publish', [...$context['params'], 'assembly' => $assembly]), ['channel_ids' => [$channel->id]])
        ->assertUnprocessable();

    expect(PublicationRequest::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('два канала ждут общего слота и повторный запрос сохраняет дату', function (string $start, string $expected, string $cron, string $timezone, int $window): void {
    config(['production.release_cron' => $cron, 'production.release_timezone' => $timezone, 'production.release_window_minutes' => $window]);
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::ACCEPTED]);
    $context['episode']->update(['accepted_assembly_id' => $assembly->id]);
    $ids = [];

    foreach ([ChannelType::YOUTUBE, ChannelType::INSTAGRAM] as $type) {
        $ids[] = Channel::factory()->recycle(workspaceOwner())->create(['project_id' => $series->project_id, 'type' => $type, 'credentials' => ['access_token' => 'test']])->id;
    }

    Bus::fake();
    Carbon::setTestNow($start);

    try {
        $route = route('episodes.assemblies.publish', [...$context['params'], 'assembly' => $assembly]);
        actingAs($context['user'])->post($route, ['channel_ids' => $ids])->assertRedirect();
        actingAs($context['user'])->post($route, ['channel_ids' => $ids, 'next_release_slot' => false])->assertRedirect();

        $requests = PublicationRequest::query()->get();
        expect($requests)->toHaveCount(2);

        $release = $requests->firstOrFail()->scheduled_at ?? throw new LogicException('Release time missing.');
        $startOfWindow = Carbon::parse($expected);
        expect($release->betweenIncluded($startOfWindow, $startOfWindow->copy()->addMinutes($window)))->toBeTrue();

        foreach ($requests as $request) {
            expect($request->scheduled_at?->equalTo($release))->toBeTrue();
            expect(app(ClaimPublication::class)->run($request))->toBeFalse();
        }

        expect(app(ReconcilePublicationsAction::class)->run())->toBe(0);
        Bus::assertNothingDispatched();

        Carbon::setTestNow($release);
        $reconcile = app(ReconcilePublicationsAction::class);
        expect($reconcile->run(1))->toBe(1);
        Carbon::setTestNow($release->copy()->addMinute());
        expect($reconcile->run(1))->toBe(1);

        foreach ($requests as $request) {
            Bus::assertDispatched(PublishEpisodeJob::class, fn(PublishEpisodeJob $job): bool => $job->requestId === $request->id);
        }

        Bus::assertDispatchedTimes(PublishEpisodeJob::class, 2);
        Http::assertNothingSent();
    } finally {
        Carbon::setTestNow();
    }
})->with([
    ['2026-11-19 23:00:00', '2026-11-20 19:00:00', '0 19 * * 2,5', 'Europe/Moscow', 0],
    ['2026-11-20 19:01:00', '2026-11-24 19:00:00', '0 19 * * 2,5', 'Europe/Moscow', 0],
    ['2026-11-20 19:01:00', '2026-11-22 19:00:00', '0 19 * * 0,2,5', 'Europe/Moscow', 30],
    ['2026-11-20 19:01:00', '2026-11-21 12:15:00', '15 16 * * 6', 'Asia/Bangkok', 30],
]);

test('заявка закрепляет готовый экспорт и проверяет его при выдаче и отправке', function (): void {
    Storage::fake('local');
    Bus::fake();
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    $revision = $assembly->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    $params = ['series' => $episode->series_id, 'episode' => $episode->id, 'revision' => $revision->id, 'assembly' => $assembly->id];
    $url = route('episodes.assemblies.publish', $params);
    $media = $assembly->addMedia(base_path('tests/Fixtures/shot.mp4'))->preservingOriginal()->toMediaCollection(EpisodeAssembly::UPSCALED);
    $checksum = hash_file('sha256', $media->getPath());
    assert(is_string($checksum));
    $op = $assembly->upscales()->create(['attempt' => 1, 'operation_key' => str_repeat('b', 64), 'initiator_id' => $user->id,
        'provider' => UpscaleProvider::FAL, 'status' => UpscaleOperationStatus::READY, 'source_snapshot' => [], 'profile_snapshot' => ['export' => '1080p'], 'currency' => 'USD', 'estimated_cost_e8' => 0]);
    $op->result_media_id = $media->id;
    $op->result_sha256 = $checksum;
    $op->save();

    $op->update(['status' => UpscaleOperationStatus::PROCESSING]);
    actingAs($user)->post($url, ['channel_ids' => [$channel->id]])->assertSessionHasNoErrors();

    $receipt = PublicationRequest::query()->sole();
    $receipt->update(['scheduled_at' => now()->subMinute()]);

    expect(app(ClaimPublication::class)->run($receipt))->toBeFalse();
    $op->update(['status' => UpscaleOperationStatus::READY]);
    expect(app(ClaimPublication::class)->run($receipt))->toBeTrue();
    expect($receipt->refresh()->payload)->toMatchArray(['media_id' => $media->id, 'sha256' => $checksum]);

    $prepared = app(PrepareEpisodePublication::class)->run($receipt)->video;
    expect($prepared->path)->toBe($media->getPath());
    get($prepared->url)->assertOk();
    $receipt->update(['status' => PublicationStatus::PLANNED]);
    File::put($media->getPath(), 'changed');
    get($prepared->url)->assertNotFound();
    $receipt->update(['scheduled_at' => now()->subMinute()]);
    expect(app(ClaimPublication::class)->run($receipt))->toBeFalse();
    expect($receipt->refresh()->status)->toBe(PublicationStatus::FAILED);
    Http::assertNothingSent();
});

test('Facebook восстанавливает выпуск после потери ответа finish', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $owner] = acceptedEpisodePublication();
    $channel->update(['type' => ChannelType::FACEBOOK, 'external_account' => '42', 'credentials' => ['channel_id' => '42', 'granted_scopes' => FacebookOAuth::SCOPES, 'access_token' => 'page-token']]);
    config(['services.facebook.manual_reels' => true]);
    Process::fake(['*ffprobe*' => Process::result(output: json_encode(['streams' => [['codec_type' => 'video', 'width' => 1080, 'height' => 1920, 'codec_name' => 'h264', 'avg_frame_rate' => '30/1']], 'format' => ['duration' => '5']], JSON_THROW_ON_ERROR))]);
    Bus::fake();
    Event::fake([PostCreated::class]);
    $revision = $assembly->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    $url = route('episodes.assemblies.publish', ['series' => $episode->series_id, 'episode' => $episode, 'revision' => $revision, 'assembly' => $assembly]);

    actingAs($owner)->post($url, ['channel_ids' => [$channel->id], 'caption' => 'Подпись'])->assertSessionHasNoErrors();
    $request = PublicationRequest::query()->sole();
    $request->update(['scheduled_at' => now()]);
    Http::fake([
        '*/42/video_reels' => Http::sequence()->push(['video_id' => '123', 'upload_url' => 'https://rupload.facebook.com/video-upload/123'])->pushFailedConnection(),
        'https://rupload.facebook.com/*' => function (Request $call) use ($request) {
            app()->call([new FinishEpisodePublicationJob($request->id), 'handle']);
            expect($call->header('offset'))->toBe(['2'])
                ->and($call->body())->toBe(substr(File::get(base_path('tests/Fixtures/shot.mp4')), 2));

            return Http::response(['success' => true]);
        },
        '*/123?fields=*' => Http::sequence()
            ->push(['status' => ['uploading_phase' => ['status' => 'in_progress', 'bytes_transfered' => 2]]])
            ->push(['status' => ['uploading_phase' => ['status' => 'completed']]])
            ->push(['id' => '123', 'from' => ['id' => '42'], 'status' => ['publishing_phase' => ['publish_status' => 'published']]]),
    ]);
    app()->call([new PublishEpisodeJob($request->id), 'handle']);
    $job = new FinishEpisodePublicationJob($request->id);
    app()->call($job->handle(...));
    app()->call($job->handle(...));

    expect($request->refresh()->provider_phase)->toBe(PublicationProviderPhase::FINISH_REQUESTED);
    config(['services.facebook.manual_reels' => false]);
    app()->call($job->handle(...));
    app()->call($job->handle(...));

    expect($request->refresh()->status)->toBe(PublicationStatus::PUBLISHED)
        ->and($request->post()->sole()->external_id)->toBe('123')
        ->and($request->post()->sole()->url)->toBeNull();
    Event::assertDispatchedTimes(PostCreated::class, 1);
});

test('отказ Facebook не меняет результат другого канала', function (string $failure): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $instagram, 'user' => $owner] = acceptedEpisodePublication();
    $channel = Channel::factory()->recycle($owner)->create(['project_id' => $instagram->project_id, 'type' => ChannelType::FACEBOOK,
        'external_account' => '42', 'credentials' => ['channel_id' => '42', 'granted_scopes' => FacebookOAuth::SCOPES, 'access_token' => 'page-token']]);
    config(['services.facebook.manual_reels' => true]);
    Process::fake(['*ffprobe*' => Process::result(output: '{"streams":[{"codec_type":"video","width":540,"height":960,"avg_frame_rate":"24/1","codec_name":"h264"}],"format":{"duration":"3"}}')]);
    Bus::fake();
    $revision = $assembly->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    $url = route('episodes.assemblies.publish', ['series' => $episode->series_id, 'episode' => $episode, 'revision' => $revision, 'assembly' => $assembly]);
    Http::fake([
        '*/42/video_reels' => match ($failure) {
            'limit' => Http::response([], 429, ['Retry-After' => '3600']),
            'timeout' => Http::failedConnection(),
            default => Http::response(['error' => ['code' => 190]], 403),
        },
        '*/ig-episode/media' => Http::response(['id' => 'instagram-container']),
    ]);

    actingAs($owner)->post($url, ['channel_ids' => [$channel->id, $instagram->id]])->assertSessionHasNoErrors();

    foreach (PublicationRequest::query()->with('channel')->get() as $request) {
        $request->update(['scheduled_at' => now()]);
        app()->call([new PublishEpisodeJob($request->id), 'handle']);
    }

    $facebook = $channel->publicationRequests()->sole();
    expect($facebook->status)->toBe(match ($failure) {
        'limit' => PublicationStatus::PLANNED,
        'timeout' => PublicationStatus::SUBMISSION_UNKNOWN,
        default => PublicationStatus::FAILED,
    })
        ->and($instagram->publicationRequests()->sole()->status)->toBe(PublicationStatus::PROCESSING);

    if ($failure === 'limit') {
        expect($facebook->scheduled_at?->gt(now()->addMinutes(59)))->toBeTrue();
    }

    Http::assertSentCount(2);
})->with(['limit', 'permission', 'timeout']);

test('Facebook проверяет закреплённый экспорт до резервирования видео', function (string $failure): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $owner] = acceptedEpisodePublication();
    config(['services.facebook.manual_reels' => true]);
    $channel->update(['type' => ChannelType::FACEBOOK, 'external_account' => '42', 'credentials' => [
        'channel_id' => '42', 'granted_scopes' => FacebookOAuth::SCOPES, 'access_token' => 'page-token',
    ]]);
    $revision = $assembly->episodeRevision()->firstOrFail();
    $episode = $revision->episode()->firstOrFail();
    $url = route('episodes.assemblies.publish', ['series' => $episode->series_id, 'episode' => $episode, 'revision' => $revision, 'assembly' => $assembly]);
    $export = $assembly->getFirstMedia(EpisodeAssembly::UPSCALED);
    expect($export)->not->toBeNull();

    if ($failure === 'checksum') {
        File::put($export?->getPath() ?? '', 'changed');
    } elseif ($failure === 'missing') {
        File::delete($export?->getPath() ?? '');
    }

    Process::fake(['*ffprobe*' => Process::result(output: '{"streams":[{"codec_type":"video","width":1280,"height":720}],"format":{"duration":"4"}}')]);

    actingAs($owner)->postJson($url, ['channel_ids' => [$channel->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('channel_ids');

    expect($channel->publicationRequests()->exists())->toBeFalse();
    Http::assertNothingSent();
})->with(['checksum', 'missing', 'horizontal']);
