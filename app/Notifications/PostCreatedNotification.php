<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Chat;
use App\Models\Post;
use App\Repositories\ChatRepository;
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

    private ?int $ownerId;

    public function __construct(private readonly Post $post)
    {
        $this->ownerId = $post->channel?->owner_id;
    }

    /** @return list<string> */
    public function via(): array
    {
        return ['telegram'];
    }

    public function shouldSend(Chat $notifiable): bool
    {
        return isset($this->ownerId) && app(ChatRepository::class)->canReceivePost($notifiable, $this->post, $this->ownerId);
    }

    public function toTelegram(): TelegramMessage
    {
        Log::info("Post Created Notification: {$this->post->id} for channel {$this->post->channel_id}");

        return TelegramMessage::create()->line($this->post->url ?? '');
    }
}
