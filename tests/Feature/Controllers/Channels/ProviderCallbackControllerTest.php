<?php

declare(strict_types=1);

use App\Classes\Channels\FacebookOAuth;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\channelOAuthState;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('YouTube callback сохраняет аккаунт и серверные токены один раз', function (): void {
    config(['services.youtube.client_id' => 'shared-client', 'services.youtube.client_secret' => 'shared-secret']);
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'client_id' => null, 'client_secret' => null]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);
    $other = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE]);
    $otherState = channelOAuthState($other);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 3600]),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'yt-1', 'snippet' => ['title' => 'Wildlife Shorts']]]]),
    ]);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'provider-code']))->assertSessionHasNoErrors()->assertRedirect(route('channels.edit', $channel));

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'channel_id' => 'yt-1', 'account_name' => 'Wildlife Shorts']);
    expect($channel->credentials['granted_scopes'] ?? null)->toBeNull();
    expect($other->refresh()->credentials)->toBeNull();
    expect(session()->has('channel_oauth.' . hash('sha256', $otherState)))->toBeTrue();
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['code'] === 'provider-code' && $request['redirect_uri'] === route('channels.provider-callback', $channel->type) && $request['client_secret'] === 'shared-secret' && $request['client_id'] === 'shared-client');
    Http::assertSentCount(2);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'provider-code']))->assertSessionHasErrors('oauth');

    Http::assertSentCount(2);
});

test('Instagram callback обменивает короткий токен и проверяет аккаунт', function (bool $wrapped): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::INSTAGRAM]);
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
    expect($channel->credentials['granted_scopes'] ?? null)->toBe(['instagram_business_basic', 'instagram_business_content_publish']);
    expect($channel->credentials['expires_at'] ?? null)->toBeString();
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/access_token?') && $request['grant_type'] === 'ig_exchange_token' && $request['access_token'] === 'short-token');
    Http::assertSentCount(3);
})->with([true, false]);

test('чужой провайдер и неизвестное состояние не получают токен', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);

    get(route('channels.provider-callback', ['instagram', 'state' => $state, 'code' => 'code']))
        ->assertRedirect(route('channels.index'))->assertSessionHasErrors('oauth');
    get(route('channels.provider-callback', ['youtube', 'state' => 'unknown', 'code' => 'code']))
        ->assertRedirect(route('channels.index'))->assertSessionHasErrors('oauth');

    Http::assertNothingSent();
    expect($channel->refresh()->credentials)->toBeNull();
});

test('TikTok использует общий ключ и сверяет неизменяемый ID автора', function (string $returned, bool $connected): void {
    config(['services.tiktok.client_id' => 'shared-key', 'services.tiktok.client_secret' => 'shared-secret']);
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::TIKTOK, 'external_account' => 'owner', 'client_id' => null, 'client_secret' => null]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);
    Http::fake([
        '*/oauth/token/' => Http::response(['access_token' => 'token', 'refresh_token' => 'refresh', 'expires_in' => 86400, 'scope' => 'user.info.basic,video.list,video.upload', 'open_id' => $returned]),
        '*/user/info/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['user' => ['open_id' => $returned, 'display_name' => 'Author']]]),
    ]);

    get(route('channels.provider-callback', ['tiktok', 'state' => $state, 'code' => 'code']))->assertRedirect(route('channels.edit', $channel));

    expect(filled($channel->refresh()->credentials))->toBe($connected);
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://open.tiktokapis.com/v2/oauth/token/' && $request['client_key'] === 'shared-key' && $request['redirect_uri'] === route('channels.tiktok-redirect'));
})->with([['owner', true], ['foreign', false]]);

test('подключение игрового TikTok закрепляет владельца без доступа к отправке роликов из Poster', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create(['type' => ChannelType::TIKTOK]);
    actingAs(serialOperator());
    $state = channelOAuthState($channel);
    Http::fake([
        '*/oauth/token/' => Http::response(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 86400, 'scope' => 'user.info.basic,video.list,video.upload', 'open_id' => 'gaming']),
        '*/user/info/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['user' => ['open_id' => 'gaming', 'display_name' => 'Games']]]),
    ]);

    get(route('channels.provider-callback', ['tiktok', 'state' => $state, 'code' => 'code']))->assertSessionHasNoErrors();

    expect($channel->refresh()->external_account)->toBe('gaming');
    expect($channel->canSendTiktokInboxVideo())->toBeFalse();
});

test('Facebook отклоняет неполные права и подмену выбранной страницы', function (bool $granted): void {
    $owner = workspaceOwner();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::FACEBOOK, 'external_account' => '41']);
    actingAs($owner);
    $state = channelOAuthState($channel);
    $permissions = $granted ? FacebookOAuth::SCOPES : ['pages_show_list'];
    Http::fake([
        '*/oauth/access_token' => Http::response(['access_token' => 'mock-user-token']),
        '*/me/permissions' => Http::response(['data' => array_map(fn(string $scope): array => ['permission' => $scope, 'status' => 'granted'], $permissions)]),
        '*/me/accounts*' => Http::response(['data' => [['id' => '42', 'tasks' => ['CREATE_CONTENT'], 'name' => 'Другая страница', 'access_token' => 'mock-page-token']]]),
    ]);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'code']))
        ->assertSessionHasErrors('oauth');

    expect($channel->refresh()->external_account)->toBe('41')
        ->and($channel->credentials)->toBeNull();
    Http::assertSentCount($granted ? 3 : 2);
})->with([true, false]);
