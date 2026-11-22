<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Data\DashboardData;
use App\Models\User;
use App\Repositories\PostRepository;
use App\Repositories\PromptRepository;

/** Читает общие данные кабинета без загрузки всей истории или вызова интеграций. */
class GetDashboard
{
    public function __construct(private readonly PostRepository $posts, private readonly PromptRepository $prompts) {}

    public function run(User $owner): DashboardData
    {
        $counts = $this->prompts->dashboardCounts($owner);

        return new DashboardData(
            promptCount: $counts['total'],
            pendingCount: $counts['total'] - $counts['ready'],
            readyCount: $counts['ready'],
            postCount: $this->posts->totalCount($owner),
            recentPrompts: $this->prompts->recentForDashboard($owner),
            recentPosts: $this->posts->recentForDashboard($owner),
        );
    }
}
