<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('форма показывает настройки и скрывает сохранённые секреты', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['type' => ChannelType::YOUTUBE, 'client_secret' => 'never-render-secret', 'credentials' => ['access_token' => 'never-render-token']]);

    $response = actingAs(serialOperator())->get(route('channels.edit', $channel));

    $response->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Channels/Form')
            ->where('channel.id', $channel->id)->where('channel.has_client_secret', true)
            ->where('callbackUrl', route('channels.provider-callback', $channel->type))->missing('channel.credentials')->missing('channel.client_secret'));

    expect($response->getContent())->not->toContain('never-render-secret', 'never-render-token');
});

test('пользователь не управляет чужим каналом', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    actingAs(User::factory()->create())->get(route('channels.edit', $channel))->assertNotFound();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    get(route('channels.edit', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->get(route('channels.edit', $channel))->assertRedirect(route('verification.notice'));
});

test('показывает запрашиваемые права TikTok отдельно от выданных', function (bool $direct): void {
    config(['services.tiktok.direct_post' => $direct]);
    $owner = workspaceOwner();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::TIKTOK,
        'external_account' => null, 'credentials' => ['access_token' => 'test', 'scope' => 'video.upload']]);
    $requested = ['user.info.basic', 'video.list', 'video.upload', ...($direct ? ['video.publish'] : [])];

    actingAs($owner)
        ->get(route('channels.edit', $channel))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('requestedScopes', $requested)
            ->where('channel.tiktok_inbox', true)
            ->where('channel.tiktok_direct', false)
            ->where('channel.tiktok_statistics', false)
            ->missing('channel.credentials'));
})->with([true, false]);
