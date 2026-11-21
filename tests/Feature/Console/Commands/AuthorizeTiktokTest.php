<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;

test('TikTok сохраняет токен после успешного обмена кода', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::TIKTOK]);

    Http::fake(['https://open.tiktokapis.com/v2/oauth/token/' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'open_id' => 'owner', 'scope' => 'user.info.basic,video.upload,video.list', 'expires_in' => 86400]),
        '*/user/info/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['user' => ['open_id' => 'owner', 'display_name' => 'Creator']]])]);

    consoleCommand('tiktok:auth', ['channel_id' => $channel->id])
        ->expectsQuestion('Paste the returned ?code=', 'test-code')
        ->assertSuccessful();

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'test-access', 'refresh_token' => 'test-refresh','channel_id' => 'owner']);
    Http::assertSent(fn(Request $request): bool => ($request['code'] ?? null) === 'test-code');
});

test('TikTok не меняет credentials при отказе API', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::TIKTOK]);

    Http::fake(['https://open.tiktokapis.com/v2/oauth/token/' => Http::response([], 400)]);

    consoleCommand('tiktok:auth', ['channel_id' => $channel->id])
        ->expectsQuestion('Paste the returned ?code=', 'test-code')
        ->assertFailed();

    expect($channel->refresh()->credentials)->toBeNull();
    Http::assertSentCount(1);
});
