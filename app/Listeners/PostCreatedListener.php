<?php

namespace App\Listeners;

use App\Events\PostCreated;
use App\Models\Chat;
use App\Notifications\PostCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class PostCreatedListener implements ShouldQueue
{
    public function handle(PostCreated $event): void
    {
        Chat::all()->each(function (Chat $chat) use ($event): void {
            $chat->notify(new PostCreatedNotification($event->post()));
        });
    }
}
