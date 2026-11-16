<?php

declare(strict_types=1);

use App\Events\PostCreated;
use App\Listeners\PostCreatedListener;
use App\Models\Chat;
use App\Models\Post;
use App\Notifications\PostCreatedNotification;
use Illuminate\Support\Facades\Notification;

test(
    'уведомление о публикации направляется всем сохранённым чатам',
    /** @throws Exception */
    function (): void {
        Notification::fake();

        $post = Post::factory()->create();
        $chats = collect(['chat-1', 'chat-2'])->map(fn(string $id) => Chat::query()->create(['chat_id' => $id]));

        app(PostCreatedListener::class)->handle(new PostCreated($post));

        Notification::assertSentTo($chats, PostCreatedNotification::class);
        expect(new PostCreatedNotification($post)
            ->toTelegram()
            ->toArray()['text'])->toContain($post->url);
    },
);
