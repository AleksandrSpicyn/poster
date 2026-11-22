<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('владелец видит только доступные режимы своего TikTok', function (bool $direct, bool $inbox): void {
    config(['services.tiktok.direct_post' => true]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->recycle($owner)->create(['type' => ChannelType::TIKTOK, 'credentials' => ['channel_id' => 'alpha',
        'account_name' => 'Alpha', 'scope' => implode(',', array_filter([$direct ? 'video.publish' : null, $inbox ? 'video.upload' : null])),
        'access_token' => 'private-token', 'expires_at' => now()->addHour()->toIso8601String()]]);
    Http::fake(['*/creator_info/query/' => Http::response(tiktokCreatorResponse())]);

    actingAs(User::factory()->create())->get(route('posts.tiktok.create', $channel))->assertNotFound();
    Http::assertNothingSent();

    $response = actingAs($owner)->get(route('posts.tiktok.create', $channel));

    if (! $direct && ! $inbox) {
        $response->assertForbidden();

        return;
    }

    $response->assertOk()->assertDontSee('private-token')->assertInertia(fn(Assert $page): Assert => $page->component('Posts/Tiktok')
        ->where('account', 'Alpha')->where('inbox', $inbox)->has('videos.data', 0)
        ->where('creator', fn($value): bool => ($value !== null) === $direct));
})->with([[false, true], [true, false], [false, false]]);

test('ошибка проверки прямой публикации сохраняет только полученные способы доставки', function (bool $inbox): void {
    config(['services.tiktok.direct_post' => true]);
    $owner = User::factory()->create();
    $channel = Channel::factory()->recycle($owner)->create([
        'type' => ChannelType::TIKTOK,
        'credentials' => [
            'channel_id' => 'alpha',
            'scope' => $inbox ? 'video.publish,video.upload' : 'video.publish',
            'access_token' => 'private-token',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ]);
    Http::fakeSequence('*/creator_info/query/')
        ->pushStatus(503)
        ->push(tiktokCreatorResponse());

    actingAs($owner)
        ->get(route('posts.tiktok.create', $channel))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('inbox', $inbox)
            ->where('creator', null)
            ->where('directUnavailable', true));

    actingAs($owner)
        ->get(route('posts.tiktok.create', $channel))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('inbox', $inbox)
            ->where('creator.nickname', 'Alpha')
            ->where('directUnavailable', false));

    Http::assertSentCount(2);
})->with([true, false]);
