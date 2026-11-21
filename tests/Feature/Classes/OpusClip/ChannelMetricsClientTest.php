<?php

declare(strict_types=1);

use App\Classes\OpusClip\ChannelMetricsClient;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('читает Reels на второй странице и сохраняет настоящий ноль', function (): void {
    $channel = Channel::factory()->make(['project_id' => null, 'type' => ChannelType::INSTAGRAM, 'external_account' => 'fixture_creator',
        'credentials' => ['account_name' => 'fixture_creator', 'channel_id' => '100', 'access_token' => 'test']]);
    Http::fake([
        '*/100/media*' => Http::sequence()->push(['data' => [], 'paging' => ['next' => 'https://opaque.invalid', 'cursors' => ['after' => 'page2']]])
            ->push(['data' => [['id' => '42', 'permalink' => 'https://www.instagram.com/reel/abc/']]]),
        '*/42/insights*' => Http::response(['data' => [['name' => 'ig_reels_avg_watch_time', 'values' => [['value' => 9700]]], ['name' => 'reach', 'values' => [['value' => 0]]]]]),
    ]);

    $metrics = iterator_to_array(app(ChannelMetricsClient::class)->read($channel, ['abc']));

    expect($metrics)->toBe(['abc' => ['averageViewDuration' => 9.7, 'reach' => 0]]);
    Http::assertSentCount(3);
    Http::assertSent(fn(Request $request): bool => ($request['after'] ?? null) === 'page2');
});

test('читает отчёт игрового YouTube после обновления токена', function (): void {
    $channel = Channel::factory()->make(['project_id' => null, 'type' => ChannelType::YOUTUBE, 'external_account' => 'UCgaming',
        'credentials' => ['channel_id' => 'UCgaming', 'access_token' => 'expired', 'refresh_token' => 'test-refresh']]);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh']),
        'youtubeanalytics.googleapis.com/*' => Http::response(['columnHeaders' => [['name' => 'video'], ['name' => 'engagedViews']],
            'rows' => [['abcdefghijk', 12]]]),
    ]);

    $metrics = iterator_to_array(app(ChannelMetricsClient::class)->read($channel, ['abcdefghijk', 'lmnopqrstuv']));

    expect($metrics)->toBe(['abcdefghijk' => ['engagedViews' => 12]]);
    Http::assertSent(fn(Request $request): bool => $request->hasHeader('Authorization', 'Bearer fresh')
        && $request['ids'] === 'channel==UCgaming' && $request['filters'] === 'video==abcdefghijk,lmnopqrstuv');
});
