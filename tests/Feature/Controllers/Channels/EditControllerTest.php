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

test('форма показывает настройки и скрывает сохранённые секреты', function (): void {
    $channel = Channel::factory()->create(['type' => ChannelType::YOUTUBE, 'client_secret' => 'never-render-secret', 'credentials' => ['access_token' => 'never-render-token']]);

    $response = actingAs(serialOperator())->get(route('channels.edit', $channel));

    $response->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page->component('Channels/Form')
            ->where('channel.id', $channel->id)->where('channel.has_client_secret', true)
            ->where('callbackUrl', route('channels.provider-callback', $channel->type))->missing('channel.credentials')->missing('channel.client_secret'));

    expect($response->getContent())->not->toContain('never-render-secret', 'never-render-token');
});

test('недопущенный пользователь не управляет каналами', function (): void {
    $channel = Channel::factory()->create();
    actingAs(User::factory()->create())->get(route('channels.edit', $channel))->assertForbidden();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->create();
    get(route('channels.edit', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->get(route('channels.edit', $channel))->assertRedirect(route('verification.notice'));
});
