<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\channelOAuthState;
use function Tests\Support\serialOperator;

test('YouTube callback сохраняет аккаунт и серверные токены один раз', function (): void {
    config(['services.youtube.client_id' => 'shared-client', 'services.youtube.client_secret' => 'shared-secret']);
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'client_id' => null, 'client_secret' => null]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);
    $other = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    $otherState = channelOAuthState($other);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 3600]),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'yt-1', 'snippet' => ['title' => 'Wildlife Shorts']]]]),
    ]);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'provider-code']))->assertSessionHasNoErrors()->assertRedirect(route('channels.edit', $channel));

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'channel_id' => 'yt-1', 'account_name' => 'Wildlife Shorts']);
    expect($other->refresh()->credentials)->toBeNull();
    expect(session()->has('channel_oauth.' . hash('sha256', $otherState)))->toBeTrue();
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['code'] === 'provider-code' && $request['redirect_uri'] === route('channels.provider-callback', $channel->type) && $request['client_secret'] === 'shared-secret' && $request['client_id'] === 'shared-client');
    Http::assertSentCount(2);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'provider-code']))->assertSessionHasErrors('oauth');

    Http::assertSentCount(2);
});

test('Instagram callback обменивает короткий токен и проверяет аккаунт', function (bool $wrapped): void {
    $channel = Channel::factory()->create(['type' => ChannelType::INSTAGRAM]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);
    $profile = ['user_id' => '12345', 'username' => 'wildlife_stories'];
    Http::fake([
        'https://api.instagram.com/oauth/access_token' => Http::response(['data' => [['access_token' => 'short-token', 'user_id' => '12345', 'permissions' => 'instagram_business_basic,instagram_business_content_publish']]]),
        'https://graph.instagram.com/access_token*' => Http::response(['access_token' => 'long-token', 'expires_in' => 5184000]),
        'https://graph.instagram.com/me*' => Http::response($wrapped ? ['data' => [$profile]] : $profile),
    ]);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'ig-code']))->assertSessionHasNoErrors()->assertRedirect(route('channels.edit', $channel));

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'long-token', 'channel_id' => '12345', 'account_name' => 'wildlife_stories']);
    expect($channel->credentials['expires_at'] ?? null)->toBeString();
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/access_token?') && $request['grant_type'] === 'ig_exchange_token' && $request['access_token'] === 'short-token');
    Http::assertSentCount(3);
})->with([true, false]);

test('чужой провайдер и неизвестное состояние не получают токен', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);

    get(route('channels.provider-callback', ['instagram', 'state' => $state, 'code' => 'code']))
        ->assertRedirect(route('channels.index'))->assertSessionHasErrors('oauth');
    get(route('channels.provider-callback', ['youtube', 'state' => 'unknown', 'code' => 'code']))
        ->assertRedirect(route('channels.index'))->assertSessionHasErrors('oauth');

    Http::assertNothingSent();
    expect($channel->refresh()->credentials)->toBeNull();
});
