<?php

declare(strict_types=1);

use App\Actions\Publications\ReconcilePublicationsAction;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\PublicationStatus;
use App\Enums\UpscaleOperationStatus;
use App\Enums\UpscaleProvider;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Tasks\Publications\ClaimPublication;
use App\Tasks\Publications\PrepareEpisodePublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
        expect(app(ReconcilePublicationsAction::class)->run())->toBe(2);
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
