<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Post;
use App\Models\Prompt;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/** Подготовленные счётчики и ограниченные выборки для обзора кабинета. */
final readonly class DashboardData
{
    /**
     * @param Closure(): DashboardStatsData $stats
     * @param Closure(): Collection<int, Prompt> $recentPrompts
     * @param Closure(): Collection<int, Post> $recentPosts
     */
    public function __construct(public Closure $stats, public Closure $recentPrompts, public Closure $recentPosts) {}
}
