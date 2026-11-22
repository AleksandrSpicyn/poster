<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('отключение убирает токены и сохраняет приложение и связи', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create(['credentials' => ['access_token' => 'saved-token']]);
    $post = Post::factory()->recycle(workspaceOwner())->for($channel)->create();

    actingAs(serialOperator())->delete(route('channels.disconnect', $channel))->assertRedirect(route('channels.edit', $channel));

    expect($channel->refresh()->credentials)->toBeNull();
    expect($channel->client_secret)->not->toBeNull();
    expect($channel->projects()->count())->toBe(1);
    expect($post->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

test('пользователь не управляет чужим каналом', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    actingAs(User::factory()->create())->delete(route('channels.disconnect', $channel))->assertNotFound();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->recycle(workspaceOwner())->create();
    delete(route('channels.disconnect', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->delete(route('channels.disconnect', $channel))->assertRedirect(route('verification.notice'));
});
