<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Jobs\PublishEpisodeJob;
use App\Models\Channel;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
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
    Bus::assertDispatched(PublishEpisodeJob::class);
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
