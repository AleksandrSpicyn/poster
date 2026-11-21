<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseCount;
use function Tests\Support\consoleCommand;

test('сохраняет показатели обеих площадок за день один раз', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'channel',
        'credentials' => ['channel_id' => 'channel', 'access_token' => 'fake', 'created' => now()->timestamp, 'expires_in' => 3600]]);
    Channel::factory()->for($project)->create(['type' => ChannelType::INSTAGRAM, 'external_account' => 'fixture_creator',
        'credentials' => ['channel_id' => '123', 'account_name' => 'fixture_creator', 'access_token' => 'fake']]);
    OpusClip::factory()->for(OpusSource::factory()->for($project), 'source')->create(['publications' => [
        ['platform' => 'youtube', 'url' => 'https://www.youtube.com/shorts/abcdefghijk'],
        ['platform' => 'instagram', 'url' => 'https://www.instagram.com/reel/abc/'],
    ]]);
    Http::fake([
        'youtubeanalytics.googleapis.com/*' => Http::response(['columnHeaders' => [['name' => 'video'], ['name' => 'views']], 'rows' => [['abcdefghijk', 15]]]),
        '*/123/media*' => Http::response(['data' => [['id' => '456', 'permalink' => 'https://www.instagram.com/reel/abc/']]]),
        '*/456/insights*' => Http::response(['data' => [
            ['name' => 'ig_reels_avg_watch_time', 'values' => [['value' => 9700]]], ['name' => 'reach', 'values' => [['value' => 0]]],
        ]]),
    ]);

    consoleCommand('opus:metrics')->assertSuccessful();
    consoleCommand('opus:metrics')->assertSuccessful();

    assertDatabaseCount('opus_clip_metrics', 2);
    $instagram = OpusClipMetric::query()->where('platform', 'instagram')->sole()->metrics;
    expect($instagram)->toHaveCount(2)
        ->and($instagram['averageViewDuration'])->toBe(9.7)
        ->and($instagram['reach'])->toBe(0)
        ->and(OpusClipMetric::query()->where('platform', 'youtube')->sole()->metrics)->toBe(['views' => 15]);
});

test('пропускает чужую авторизацию до запроса метрик', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    Channel::factory()->for($project)->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'gaming',
        'credentials' => ['channel_id' => 'series', 'access_token' => 'fake']]);
    OpusClip::factory()->for(OpusSource::factory()->for($project), 'source')->create(['publications' => [['url' => 'https://youtu.be/abcdefghijk', 'platform' => 'youtube']]]);

    consoleCommand('opus:metrics')->assertFailed();

    Http::assertNothingSent();
    assertDatabaseCount('opus_clip_metrics', 0);
});
