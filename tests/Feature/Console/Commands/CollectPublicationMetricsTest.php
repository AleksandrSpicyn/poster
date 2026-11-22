<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\ClippingAccountMapping;
use App\Models\ClippingConnection;
use App\Models\ClippingSyncRun;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationObservation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Tests\Support\consoleCommand;

test('объединяет собственный ролик и импорт по подтверждённому аккаунту и media ID', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE,
        'credentials' => ['channel_id' => 'UC-studio', 'access_token' => 'fake', 'created' => now()->timestamp, 'expires_in' => 3600]]);
    Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'UC-studio']);
    $prompt = Prompt::factory()->for($project)->create();
    Post::factory()->for($prompt)->for($channel)->create(['url' => 'https://youtu.be/abcdefghijk']);
    Post::factory()->for($prompt)->for($channel)->create(['url' => 'https://youtu.be/other012345']);
    $connection = ClippingConnection::factory()->create(['owner_id' => $owner->id]);
    ClippingAccountMapping::query()->create(['clipping_connection_id' => $connection->id, 'provider_account_id' => 'profile',
        'platform' => 'youtube', 'external_id' => 'UC-studio', 'channel_id' => $channel->id]);
    $clip = OpusClip::factory()->for(OpusSource::factory()->for($project)->state(['clipping_connection_id' => $connection->id]), 'source')
        ->create(['publications' => [['platform' => 'youtube', 'url' => 'https://www.youtube.com/shorts/abcdefghijk']]]);

    $run = ClippingSyncRun::query()->create(['opus_source_id' => $clip->opus_source_id, 'completed_at' => now()]);
    $clip->latestMetrics()->create(['platform' => 'youtube', 'media_id' => 'abcdefghijk', 'observed_on' => now('UTC')->toDateString(), 'metrics' => ['views' => 77]]);
    Http::fake(['*/youtube/v3/videos*' => Http::response(['items' => [
        ['id' => 'abcdefghijk', 'snippet' => ['channelId' => 'UC-studio'], 'statistics' => ['viewCount' => '77', 'likeCount' => '0']],
        ['id' => 'other012345', 'snippet' => ['channelId' => 'another'], 'statistics' => ['viewCount' => '50']],
    ]])]);

    consoleCommand('publications:metrics')->assertSuccessful();
    consoleCommand('publications:metrics')->assertSuccessful();

    assertDatabaseCount('clipping_sync_observations', 1);
    assertDatabaseHas('clipping_sync_observations', ['clipping_sync_run_id' => $run->id, 'opus_source_id' => $clip->opus_source_id]);
    assertDatabaseCount('publication_observations', 1);
    assertDatabaseCount('publication_metric_origins', 2);
    $observation = PublicationObservation::query()->sole();
    expect($observation->metrics)->toEqual(['views' => 77, 'likes' => 0])
        ->and($observation->opus_clip_id)->toBe($clip->id)
        ->and($observation->post_id)->not->toBeNull();
    actingAs($owner);
    get(route('dashboard'))->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 1)
        ->where('analytics.publications.0.url', 'https://youtu.be/abcdefghijk')
        ->where('analytics.publications.0.snapshots.' . now('UTC')->toDateString(), 77));
    get(route('dashboard.analytics', ['project' => $project->id]))->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 1));
    get(route('dashboard.analytics', ['project' => $project->id, 'channel' => $channel->id]))
        ->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 1)
            ->where('analytics.channelReport.observations.' . now('UTC')->toDateString(), 77));
    actingAs(User::factory()->create());
    get(route('dashboard.analytics', ['project' => $project->id]))->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 0)->has('analytics.projects', 0));
    get(route('dashboard'))->assertInertia(fn(Assert $page): Assert => $page->has('analytics.publications', 0));
    Http::assertSentCount(2);
});

test('продолжает сбор после таймаута и пропускает канал без video.list', function (): void {
    $project = Project::factory()->create();
    $prompt = Prompt::factory()->for($project)->create();
    $youtube = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE,
        'credentials' => ['channel_id' => 'yt', 'access_token' => 'fake', 'created' => now()->timestamp, 'expires_in' => 3600]]);
    Post::factory()->for($prompt)->for($youtube)->create(['url' => 'https://youtu.be/abcdefghijk']);
    $instagram = Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM,
        'credentials' => ['channel_id' => '123', 'account_name' => 'studio', 'access_token' => 'fake']]);
    Post::factory()->for($prompt)->for($instagram)->create(['url' => 'https://www.instagram.com/reel/sunset/']);
    $tiktok = Channel::factory()->for($project)->create(['type' => ChannelType::TIKTOK,
        'credentials' => ['channel_id' => 'tt', 'scope' => 'video.upload', 'access_token' => 'fake']]);
    Post::factory()->for($prompt)->for($tiktok)->create(['url' => 'https://www.tiktok.com/@studio/video/123']);
    Http::fake([
        '*/youtube/v3/videos*' => Http::failedConnection(),
        '*/123/media*' => Http::response(['data' => [['id' => '321', 'permalink' => 'https://www.instagram.com/reel/sunset/', 'like_count' => 0, 'comments_count' => 2]]]),
    ]);

    consoleCommand('publications:metrics')->assertFailed();

    expect(PublicationObservation::query()->sole()->metrics)->toEqual(['likes' => 0, 'comments' => 2]);
    assertDatabaseHas('channel_metric_syncs', ['channel_id' => $youtube->id, 'status' => 'temporary_error']);
    assertDatabaseHas('channel_metric_syncs', ['channel_id' => $tiktok->id, 'status' => 'no_permission']);
    assertDatabaseHas('channel_metric_syncs', ['channel_id' => $instagram->id, 'status' => 'collected']);
});

test('фиксирует ограничение платформы отдельно от временных ошибок', function (): void {
    $project = Project::factory()->create();
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE,
        'credentials' => ['channel_id' => 'studio', 'access_token' => 'fake', 'created' => now()->timestamp, 'expires_in' => 3600]]);
    $prompt = Prompt::factory()->for($project)->create();
    Post::factory()->for($prompt)->for($channel)->create(['url' => 'https://youtu.be/abcdefghijk']);
    Http::fake(['*/youtube/v3/videos*' => Http::response([], 429)]);

    consoleCommand('publications:metrics')->assertFailed();

    assertDatabaseHas('channel_metric_syncs', ['channel_id' => $channel->id, 'status' => 'rate_limited']);
    assertDatabaseCount('clipping_sync_observations', 0);
});
