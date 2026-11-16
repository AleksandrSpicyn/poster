<?php

declare(strict_types=1);

use App\Actions\Episodes\RequestEpisodePublicationAction;
use App\Data\Episodes\PublishEpisodeData;
use App\Models\Channel;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\delete;
use function Tests\Support\acceptedEpisodePublication;
use function Tests\Support\serialOperator;

test('канал без связей и истории удаляется', function (): void {
    $channel = Channel::factory()->create(['project_id' => null]);

    actingAs(serialOperator())->delete(route('channels.destroy', $channel))->assertRedirect(route('channels.index'));

    assertDatabaseCount('channels', 0);
    Http::assertNothingSent();
});

test('связанный канал сохраняется при попытке удаления', function (): void {
    $channel = Channel::factory()->create();

    actingAs(serialOperator())->delete(route('channels.destroy', $channel))->assertSessionHasErrors('channel');

    expect($channel->fresh())->not->toBeNull();
    expect($channel->projects()->count())->toBe(1);
});

test('история публикации запрещает удаление даже без проектов', function (): void {
    $channel = Channel::factory()->create(['project_id' => null]);
    $post = Post::factory()->for($channel)->create();

    actingAs(serialOperator())->delete(route('channels.destroy', $channel))->assertSessionHasErrors('channel');

    expect($channel->fresh())->not->toBeNull();
    expect($post->fresh())->not->toBeNull();
});

test('недопущенный пользователь не управляет каналами', function (): void {
    $channel = Channel::factory()->create();
    actingAs(User::factory()->create())->delete(route('channels.destroy', $channel))->assertForbidden();

    Http::assertNothingSent();
});

test('гость и неподтверждённый пользователь не открывают канал', function (): void {
    $channel = Channel::factory()->create();
    delete(route('channels.destroy', $channel))->assertRedirect(route('login'));

    actingAs(User::factory()->unverified()->create())->delete(route('channels.destroy', $channel))->assertRedirect(route('verification.notice'));
});

test('неотправленный запрос публикации защищает историю канала', function (): void {
    Storage::fake('local');
    ['assembly' => $assembly, 'channel' => $channel, 'user' => $user] = acceptedEpisodePublication();
    Queue::fake();
    $request = app(RequestEpisodePublicationAction::class)->run($assembly, new PublishEpisodeData([$channel->id]), $user)->sole();
    $channel->projects()->detach();

    actingAs($user)->delete(route('channels.destroy', $channel))->assertSessionHasErrors('channel');

    expect($channel->fresh())->not->toBeNull();
    expect($request->fresh())->not->toBeNull();
    Http::assertNothingSent();
});
