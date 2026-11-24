<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Chat;
use App\Models\Post;
use Illuminate\Support\LazyCollection;

/** @extends EloquentRepository<Chat> */
readonly class ChatRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Chat::class;
    }

    public function canReceivePost(Chat $chat, Post $post, int $ownerId): bool
    {
        return Chat::query()
            ->join('users', 'users.id', '=', 'chats.owner_id')
            ->join('channels', 'channels.owner_id', '=', 'users.id')
            ->join('posts', 'posts.channel_id', '=', 'channels.id')
            ->where('chats.id', $chat->id)->where('chats.chat_id', $chat->chat_id)
            ->where('users.id', $ownerId)->whereNotNull('users.email_verified_at')
            ->where('posts.id', $post->id)->exists();
    }

    /** @return LazyCollection<int, Chat> */
    public function forNotifications(?int $ownerId): LazyCollection
    {
        return Chat::query()->whereNotNull('owner_id')->where('owner_id', $ownerId)->lazyById();
    }
}
