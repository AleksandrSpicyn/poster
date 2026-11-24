<?php

declare(strict_types=1);

use App\Events\PostCreated;
use App\Listeners\PostCreatedListener;
use App\Models\Chat;
use App\Models\Post;
use App\Notifications\PostCreatedNotification;
use Illuminate\Support\Facades\Notification;

use function Tests\Support\workspaceOwner;

test(
    'уведомление о публикации направляется чатам владельца',
    /** @throws Exception */
    function (): void {
        Notification::fake();

        $post = Post::factory()->recycle(workspaceOwner())->create();
        $chats = collect(['chat-1', 'chat-2'])->map(fn(string $id) => Chat::query()->forceCreate(['chat_id' => $id, 'owner_id' => workspaceOwner()->id]));

        app(PostCreatedListener::class)->handle(new PostCreated($post));

        Notification::assertSentTo($chats, PostCreatedNotification::class);
        expect(new PostCreatedNotification($post)
            ->toTelegram()
            ->toArray()['text'])->toContain($post->url);
    },
);

test('смена владельца чата после постановки в очередь отменяет доставку', function (): void {
    $owner = workspaceOwner();
    $post = Post::factory()->recycle($owner)->create();
    $chat = Chat::query()->forceCreate(['chat_id' => 'old-chat', 'owner_id' => $owner->id]);
    $notification = new PostCreatedNotification($post);
    expect($notification->shouldSend($chat))->toBeTrue();

    Chat::query()->whereKey($chat->id)->update(['owner_id' => null]);

    expect($notification->shouldSend($chat))->toBeFalse();
});

test('уведомление из очереди без владельца пропускает доставку', function (): void {
    $owner = workspaceOwner();
    $post = Post::factory()->recycle($owner)->create();
    $chat = Chat::query()->forceCreate(['chat_id' => 'historical-chat', 'owner_id' => $owner->id]);
    $payload = new PostCreatedNotification($post)->__serialize();
    unset($payload["\0" . PostCreatedNotification::class . "\0ownerId"]);
    $notification = new ReflectionClass(PostCreatedNotification::class)->newInstanceWithoutConstructor();

    $notification->__unserialize($payload);

    expect($notification->shouldSend($chat))->toBeFalse();
});
