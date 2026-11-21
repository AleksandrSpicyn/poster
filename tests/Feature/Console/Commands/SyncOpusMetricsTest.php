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

test('TikTok обновляет токен игрового канала и сохраняет показатели его клипа', function (): void {
    $project = Project::factory()->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $channel = Channel::factory()->for($project)->create(['type' => ChannelType::TIKTOK, 'external_account' => 'gaming',
        'credentials' => ['channel_id' => 'gaming', 'access_token' => 'expired', 'refresh_token' => 'old', 'expires_at' => now()->subHour()->toIso8601String()]]);
    OpusClip::factory()->for(OpusSource::factory()->for($project), 'source')->create(['publications' => [
        ['platform' => 'tiktok', 'url' => 'https://www.tiktok.com/@player/video/123456789'],
    ]]);
    Http::fake([
        '*/oauth/token/' => Http::response(['open_id' => 'gaming', 'access_token' => 'fresh', 'refresh_token' => 'rotated', 'expires_in' => 86400, 'scope' => 'video.list']),
        '*/video/query/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['videos' => [
            ['id' => '123456789', 'view_count' => 123, 'like_count' => 0,'comment_count' => 2,'share_count' => 1],
        ]]]),
    ]);

    consoleCommand('opus:metrics')->assertSuccessful();
    consoleCommand('opus:metrics')->assertSuccessful();

    expect($channel->refresh()->credentials['refresh_token'] ?? null)->toBe('rotated')
        ->and(OpusClipMetric::query()->sole()->metrics)->toMatchArray(['views' => 123, 'likes' => 0, 'comments' => 2, 'shares' => 1]);
    Http::assertSentCount(3);
});
