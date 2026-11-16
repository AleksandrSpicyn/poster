<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Chat;
use Illuminate\Support\LazyCollection;

/** @extends EloquentRepository<Chat> */
readonly class ChatRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Chat::class;
    }

    /** @return LazyCollection<int, Chat> */
    public function forNotifications(): LazyCollection
    {
        return Chat::query()->lazyById();
    }
}
