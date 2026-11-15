<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\Chat;
use App\Notifications\PostCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * В очереди рассылает уведомление о сохранённой публикации всем Chat.
 * Каждое PostCreatedNotification также доставляется как отдельная queued notification.
 */
class PostCreatedListener implements ShouldQueue
{
    public function handle(PostCreated $event): void
    {
        Chat::query()->lazyById()->each(function (Chat $chat) use ($event): void {
            $chat->notify(new PostCreatedNotification($event->post()));
        });
    }
}
