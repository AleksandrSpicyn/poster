<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Chat;
use App\Models\Post;
use App\Models\User;
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
        return $this->ownerId !== null && $notifiable->owner_id === $this->ownerId
            && User::query()->whereKey($this->ownerId)->whereNotNull('email_verified_at')->exists()
            && $this->post->channel()->where('owner_id', $this->ownerId)->exists();
    }

    public function toTelegram(): TelegramMessage
    {
        Log::info("Post Created Notification: {$this->post->id} for channel {$this->post->channel_id}");

        return TelegramMessage::create()->line($this->post->url ?? '');
    }
}
