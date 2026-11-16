<?php

declare(strict_types=1);

namespace App\Tasks\Chats;

use App\Models\Chat;
use App\Repositories\ChatRepository;
use Illuminate\Support\LazyCollection;

readonly class GetNotificationChats
{
    public function __construct(private ChatRepository $chats) {}

    /** @return LazyCollection<int, Chat> */
    public function run(): LazyCollection
    {
        return $this->chats->forNotifications();
    }
}
