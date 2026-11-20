<?php

declare(strict_types=1);

use App\Actions\Publications\ReconcilePublicationsAction;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Tasks\Publications\ClaimPublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;

test('публикация создаёт receipt для выбранного канала без внешней отправки в HTTP', function (): void {
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::ACCEPTED]);
    $context['episode']->update(['accepted_assembly_id' => $assembly->id]);
    $channel = Channel::factory()->create(['project_id' => $series->project_id, 'type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'test-token']]);
    Bus::fake();

    actingAs($context['user'])
        ->post(route('episodes.assemblies.publish', [...$context['params'], 'assembly' => $assembly]), ['channel_ids' => [$channel->id]])
        ->assertRedirect();

    $request = PublicationRequest::query()->firstOrFail();
    expect($request->episode_assembly_id)->toBe($assembly->id);
    expect($request->channel_id)->toBe($channel->id);
    expect($request->scheduled_at?->isFuture())->toBeTrue();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('черновик не создаёт публикацию', function (): void {
    $context = episodeHttpFixture();
    $assembly = EpisodeAssembly::factory()->for($context['revision'])->create(['status' => AssemblyStatus::READY]);
    $channel = Channel::factory()->create();
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
        $ids[] = Channel::factory()->create(['project_id' => $series->project_id, 'type' => $type, 'credentials' => ['access_token' => 'test']])->id;
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
    ['2026-11-20 19:01:00', '2026-11-21 12:15:00', '15 16 * * 6', 'Asia/Bangkok', 30],
]);
