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
                ['id' => '22', 'name' => 'Вторая', 'tasks' => ['CREATE_CONTENT'], 'access_token' => 'second-secret'],
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
