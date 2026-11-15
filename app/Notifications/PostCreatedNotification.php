<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Telegram\TelegramMessage;

/**
 * Доставляет ссылку сохранённого Post в Telegram-чат через очередь уведомлений.
 */
class PostCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly Post $post) {}

    /** @return list<string> */
    public function via(): array
    {
        return ['telegram'];
    }

    public function toTelegram(): TelegramMessage
    {
        Log::info("Post Created Notification: {$this->post->id} for channel {$this->post->channel_id}");

        return TelegramMessage::create()->line($this->post->url ?? '');
    }
}
