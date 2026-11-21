<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;

test('получает страницы публичных роликов выбранного автора', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::TIKTOK, 'credentials' => ['channel_id' => 'series-owner',
        'expires_at' => now()->addHour()->toIso8601String(), 'access_token' => 'series-access']]);
    Http::fake(['*/video/list/*' => Http::sequence()
        ->push(['error' => ['code' => 'ok'], 'data' => ['videos' => [['id' => '123', 'title' => 'Episode', 'view_count' => 12, 'like_count' => 3, 'comment_count' => 0, 'share_count' => 1]], 'has_more' => true, 'cursor' => 1234]])
        ->push(['error' => ['code' => 'ok'], 'data' => ['videos' => [], 'has_more' => false]])]);

    consoleCommand('tiktok:metrics', ['channel' => $channel->id])
        ->expectsTable(['Video ID', 'Title', 'Views', 'Likes', 'Comments', 'Shares'], [['123','Episode','12','3','0','1']])
        ->assertSuccessful();

    Http::assertSent(fn(Request $request): bool => $request->hasHeader('Authorization', 'Bearer series-access') && ($request['cursor'] ?? null) === 1234);
    Http::assertSentCount(2);
});
