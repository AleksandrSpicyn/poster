<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\channelOAuthState;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

function legacyChannelOAuthState(Channel $channel): string
{
    $state = channelOAuthState($channel);
    session()->forget('channel_oauth.' . hash('sha256', $state) . '.provider');

    return $state;
}

test('YouTube callback сохраняет аккаунт и серверные токены один раз', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE]);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 3600]),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'yt-1', 'snippet' => ['title' => 'Wildlife Shorts']]]]),
    ]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'provider-code']))->assertSessionHasNoErrors()->assertRedirect(route('channels.edit', $channel));

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'channel_id' => 'yt-1', 'account_name' => 'Wildlife Shorts']);
    Http::assertSent(fn(Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['code'] === 'provider-code' && $request['redirect_uri'] === route('channels.callback', $channel) && $request['client_secret'] === $channel->client_secret);
    Http::assertSentCount(2);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'provider-code']))->assertSessionHasErrors('oauth');

    Http::assertSentCount(2);
});

test('Instagram callback обменивает короткий токен и проверяет аккаунт', function (bool $wrapped): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::INSTAGRAM]);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    $profile = ['user_id' => '12345', 'username' => 'wildlife_stories'];
    Http::fake([
        'https://api.instagram.com/oauth/access_token' => Http::response(['data' => [['access_token' => 'short-token', 'user_id' => '12345', 'permissions' => 'instagram_business_basic,instagram_business_content_publish']]]),
        'https://graph.instagram.com/access_token*' => Http::response(['access_token' => 'long-token', 'expires_in' => 5184000]),
        'https://graph.instagram.com/me*' => Http::response($wrapped ? ['data' => [$profile]] : $profile),
    ]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'ig-code']))->assertSessionHasNoErrors()->assertRedirect(route('channels.edit', $channel));

    expect($channel->refresh()->credentials)->toMatchArray(['access_token' => 'long-token', 'channel_id' => '12345', 'account_name' => 'wildlife_stories']);
    expect($channel->credentials['expires_at'] ?? null)->toBeString();
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), '/access_token?') && $request['grant_type'] === 'ig_exchange_token' && $request['access_token'] === 'short-token');
    Http::assertSentCount(3);
})->with([true, false]);

test('негодное состояние и отказ провайдера не меняют прежнее подключение', function (string $scenario): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'old-token']]);
    $actor = serialOperator();
    actingAs($actor);
    $state = legacyChannelOAuthState($channel);
    $pendingKey = 'channel_oauth.' . hash('sha256', $state);
    $params = ['state' => $state, 'code' => 'code'];

    match ($scenario) {
        'unknown' => $params['state'] = 'unknown',
        'expired' => session()->put($pendingKey . '.expires_at', 0),
        'user' => actingAs(User::factory()->create()),
        'channel' => $channel->update(['client_id' => 'changed-app']),
        'cancelled' => $params = ['state' => $state, 'error' => 'access_denied'],
        default => throw new InvalidArgumentException('Unknown test scenario.'),
    };

    $response = get(route('channels.callback', [$channel, ...$params]));

    if ($scenario === 'user') {
        $response->assertNotFound();
    } else {
        $response->assertSessionHasErrors('oauth');
    }

    expect($channel->refresh()->credentials)->toBe(['access_token' => 'old-token']);
    Http::assertNothingSent();
})->with(['unknown', 'expired', 'user', 'channel', 'cancelled']);

test('ошибка провайдера не раскрывает токены и не стирает подключение', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'old-token']]);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['error' => 'provider-secret-detail'], 400)]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'code']))
        ->assertSessionHasErrors('oauth')
        ->assertSessionHas('errors', static fn(ViewErrorBag $errors): bool => ! str_contains($errors->first('oauth'), 'provider-secret-detail'));
    expect($channel->refresh()->credentials)->toBe(['access_token' => 'old-token']);
    Http::assertSentCount(1);
});

test('смена приложения во время обмена не сохраняет токены', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE]);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake([
        'https://oauth2.googleapis.com/token' => function () use ($channel) {
            $channel->update(['client_secret' => 'changed-secret']);

            return Http::response(['access_token' => 'access-1', 'refresh_token' => 'refresh-1']);
        },
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'yt-1', 'snippet' => ['title' => 'Wildlife']]]]),
    ]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'code']))->assertSessionHasErrors('oauth');

    expect($channel->refresh()->credentials)->toBeNull();
    Http::assertSentCount(2);
});

test('refresh token сохраняется только для того же YouTube аккаунта', function (string $account, bool $succeeds): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'old-token', 'refresh_token' => 'old-refresh', 'channel_id' => 'original']]);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-token']),
        'https://www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => $account, 'snippet' => ['title' => 'Wildlife']]]]),
    ]);

    $response = get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'code']));

    if ($succeeds) {
        $response->assertSessionHasNoErrors();
        expect($channel->refresh()->credentials['access_token'] ?? null)->toBe('new-token');
        expect($channel->credentials['refresh_token'] ?? null)->toBe('old-refresh');
    } else {
        $response->assertSessionHasErrors('oauth');
        expect($channel->refresh()->credentials['access_token'] ?? null)->toBe('old-token');
    }

    Http::assertSentCount(2);
})->with([['original', true], ['other', false]]);

test('пользователь не управляет чужим каналом', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    actingAs(User::factory()->create())->get(route('channels.callback', $channel))->assertNotFound();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    get(route('channels.callback', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->get(route('channels.callback', $channel))->assertRedirect(route('verification.notice'));
});

test('отсутствие права публикации не подключает Instagram', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::INSTAGRAM]);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake(['https://api.instagram.com/oauth/access_token' => Http::response(['data' => [
        ['access_token' => 'short-token', 'permissions' => 'instagram_business_basic'],
    ]])]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'code']))->assertSessionHasErrors('oauth');

    expect($channel->refresh()->credentials)->toBeNull();
    Http::assertSentCount(1);
});

test('игровой канал отклоняет подключение другого аккаунта', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'UCgaming']);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'refresh']),
        '*/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UCother', 'snippet' => ['title' => 'Other channel']]]]),
    ]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'code']))
        ->assertSessionHasErrors(['oauth' => 'YouTube did not return the assigned channel for this login. In the Google account chooser, select the YouTube channel or Brand Account that owns it.']);

    expect($channel->refresh()->credentials)->toBeNull();
});

test('игровой канал находится на следующей странице даже без названия', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'external_account' => 'UCgaming']);
    actingAs(serialOperator());
    $state = legacyChannelOAuthState($channel);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'refresh']),
        '*/youtube/v3/channels*' => Http::sequence()
            ->push(['items' => [['id' => 'UCother']], 'nextPageToken' => 'next'])
            ->push(['items' => [['id' => 'UCgaming']]]),
    ]);

    get(route('channels.callback', [$channel, 'state' => $state, 'code' => 'code']))
        ->assertSessionHasNoErrors();

    expect($channel->refresh()->credentials)->toMatchArray(['account_name' => 'UCgaming', 'channel_id' => 'UCgaming']);
    Http::assertSent(fn(Request $request): bool => str_contains($request->url(), 'pageToken=next') && str_contains($request->url(), 'mine=true'));
});
