<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\Chat;
use App\Notifications\PostCreatedNotification;
use App\Tasks\Chats\GetNotificationChats;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * В очереди рассылает уведомление о сохранённой публикации чатам владельца.
 * Каждое PostCreatedNotification также доставляется как отдельная queued notification.
 */
class PostCreatedListener implements ShouldQueue
{
    public function __construct(private readonly GetNotificationChats $chats) {}

    public function handle(PostCreated $event): void
    {
        if ($event->ownerId === null || $event->post()->channel?->owner_id !== $event->ownerId) {
            return;
        }

        $this->chats->run($event->ownerId)->each(function (Chat $chat) use ($event): void {
            $chat->notify(new PostCreatedNotification($event->post()));
        });
    }
}
