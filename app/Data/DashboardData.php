<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Post;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

/** Подготовленные счётчики и ограниченные выборки для обзора кабинета. */
class DashboardData extends Data
{
    /**
     * @param Collection<int, Prompt> $recentPrompts
     * @param Collection<int, Post> $recentPosts
     */
    public function __construct(
        public int $promptCount,
        public int $pendingCount,
        public int $readyCount,
        public int $postCount,
        public Collection $recentPrompts,
        public Collection $recentPosts,
    ) {}
}
