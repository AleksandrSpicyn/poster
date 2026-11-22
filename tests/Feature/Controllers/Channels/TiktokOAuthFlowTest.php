<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\channelOAuthState;

test('штатный callback через зарегистрированный адрес сохраняет выданные права один раз', function (string $scope): void {
    $owner = User::factory()->create();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::TIKTOK]);
    actingAs($owner);
    $state = channelOAuthState($channel);
    Http::fake([
        '*/oauth/token/' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'expires_in' => 3600, 'open_id' => 'alpha', 'scope' => $scope]),
        '*/user/info/*' => Http::response(['error' => ['code' => 'ok'], 'data' => ['user' => ['open_id' => 'alpha', 'display_name' => 'Alpha']]]),
    ]);

    $query = ['state' => $state, 'code' => 'test-code'];
    $callback = route('channels.provider-callback', ['tiktok', ...$query]);
    get(route('channels.tiktok-redirect', $query))->assertRedirect($callback);
    get($callback)->assertRedirect(route('channels.edit', $channel))->assertSessionHasNoErrors();
    get($callback)->assertSessionHasErrors('oauth');

    expect($channel->refresh()->credentials)->toMatchArray(['scope' => $scope, 'channel_id' => 'alpha'])
        ->and($channel->getRawOriginal('credentials'))->not->toContain('test-access')
        ->and($channel->canSendTiktokInboxVideo())->toBe(str_contains($scope, 'video.upload'));
    Http::assertSentCount(2);
})->with(['user.info.basic', 'user.info.basic,video.upload']);

test('callback отбрасывает чужое состояние, истечение срока и отказ', function (string $scenario): void {
    $owner = User::factory()->create();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::TIKTOK]);
    actingAs($owner);
    $state = channelOAuthState($channel);
    $query = ['state' => $state, 'code' => 'test'];
    match ($scenario) {
        'owner' => actingAs(User::factory()->create()),
        'expired' => session()->put('channel_oauth.' . hash('sha256', $state) . '.expires_at', 0),
        'modified' => $query['state'] = 'modified',
        'declined' => $query = ['state' => $state, 'error' => 'access_denied'],
        default => throw new InvalidArgumentException('Unknown scenario.'),
    };

    $callback = route('channels.provider-callback', ['tiktok', ...$query]);
    get(route('channels.tiktok-redirect', $query))->assertRedirect($callback);
    get($callback)->assertRedirect()->assertSessionHasErrors('oauth');

    expect($channel->refresh()->credentials)->toBeNull();
    Http::assertNothingSent();
})->with(['owner', 'expired', 'modified', 'declined']);
