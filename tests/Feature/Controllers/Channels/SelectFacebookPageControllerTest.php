<?php

declare(strict_types=1);

use App\Classes\Channels\FacebookOAuth;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Tests\Support\channelOAuthState;
use function Tests\Support\workspaceOwner;

test('владелец выбирает доступную страницу Facebook только один раз', function (): void {
    $owner = workspaceOwner();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::FACEBOOK]);
    actingAs($owner);
    $state = channelOAuthState($channel);
    Http::fake([
        '*/oauth/access_token' => Http::response(['access_token' => 'user-token']),
        '*/me/permissions' => Http::response(['data' => array_map(fn(string $scope): array => ['permission' => $scope, 'status' => 'granted'], FacebookOAuth::SCOPES)]),
        '*/me/accounts*' => Http::sequence()
            ->push(['data' => [['id' => '21', 'name' => 'Первая', 'tasks' => ['CREATE_CONTENT'], 'access_token' => 'first-secret']], 'paging' => ['next' => 'next', 'cursors' => ['after' => 'cursor']]])
            ->push(['data' => [
                ['id' => '22', 'name' => 'Вторая', 'tasks' => ['PROFILE_PLUS_CREATE_CONTENT'], 'access_token' => 'second-secret'],
                ['id' => '23', 'name' => 'Чужая', 'tasks' => ['ANALYZE']],
            ]]),
    ]);

    get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'code']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('channels.edit', $channel));
    get(route('channels.edit', $channel))
        ->assertDontSee('second-secret')
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('facebookPages', [['id' => '21', 'name' => 'Первая'], ['id' => '22', 'name' => 'Вторая']]));
    $selection = route('channels.facebook-page', ['channel' => $channel, 'page' => '22']);
    post($selection)->assertRedirect();

    expect($channel->refresh()->external_account)->toBe('22')
        ->and($channel->credentials['access_token'] ?? null)->toBe('second-secret');
    post($selection)->assertSessionHasErrors('oauth');
    Http::assertSentCount(4);
});

test('точная страница Facebook доступна при пустом списке только с обоими подтверждёнными правами', function (string $scenario): void {
    $owner = workspaceOwner();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::FACEBOOK, 'external_account' => null, 'client_id' => null, 'client_secret' => null]);
    config()->set([
        'services.facebook.client_id' => '1234',
        'services.facebook.client_secret' => 'app-secret',
        'services.facebook.direct_page_channel_id' => (string) $channel->id,
        'services.facebook.direct_page_id' => '42',
    ]);
    actingAs($owner);
    $state = channelOAuthState($channel);
    $debugData = static fn(array $targets): array => [
        'is_valid' => true,
        'app_id' => '1234',
        'granular_scopes' => [['scope' => 'pages_manage_posts', 'target_ids' => $targets]],
    ];
    Http::preventStrayRequests();
    Http::fake([
        '*/oauth/access_token' => Http::response(['access_token' => 'user-token']),
        '*/me/permissions' => Http::response(['data' => array_map(fn(string $scope): array => ['permission' => $scope, 'status' => 'granted'], FacebookOAuth::SCOPES)]),
        '*/me/accounts*' => Http::response(['data' => $scenario === 'list_denied' ? [['id' => '42', 'tasks' => ['ANALYZE']]] : []]),
        '*/42?*' => Http::response(['id' => '42', 'name' => 'Тестовая страница', 'access_token' => 'page-secret']),
        '*/debug_token?*input_token=user-token' => Http::response(['data' => $debugData($scenario === 'user_denied' ? ['99'] : ['42'])]),
        '*/debug_token?*input_token=page-secret' => Http::response(['data' => [...$debugData($scenario === 'page_denied' ? ['99'] : ['42']),
            'type' => 'PAGE', 'profile_id' => $scenario === 'wrong_profile' ? '99' : '42']]),
    ]);

    $callback = get(route('channels.provider-callback', [$channel->type, 'state' => $state, 'code' => 'code']));

    if ($scenario !== 'eligible') {
        $callback->assertSessionHasErrors('oauth');
        expect($channel->refresh()->external_account)->toBeNull();

        return;
    }

    $callback->assertSessionHasNoErrors();
    get(route('channels.edit', $channel))
        ->assertDontSee('page-secret')
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('facebookPages', [['id' => '42', 'name' => 'Тестовая страница']]));
    post(route('channels.facebook-page', ['channel' => $channel, 'page' => '42']))->assertRedirect();

    expect($channel->refresh()->external_account)->toBe('42')
        ->and($channel->credentials['access_token'] ?? null)->toBe('page-secret');
})->with(['eligible', 'user_denied', 'page_denied', 'wrong_profile', 'list_denied']);
