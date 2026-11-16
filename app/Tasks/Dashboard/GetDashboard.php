<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Data\DashboardData;
use App\Repositories\PostRepository;
use App\Repositories\PromptRepository;

/** Читает общие данные кабинета без загрузки всей истории или вызова интеграций. */
class GetDashboard
{
    public function __construct(private readonly PostRepository $posts, private readonly PromptRepository $prompts) {}

    public function run(): DashboardData
    {
        $promptCount = $this->prompts->legacyCount();
        $readyCount = $this->prompts->readyCount();

        return new DashboardData(
            promptCount: $promptCount,
            pendingCount: $this->prompts->pendingCount(),
            readyCount: $readyCount,
            postCount: $this->posts->totalCount(),
            recentPrompts: $this->prompts->recentForDashboard(),
            recentPosts: $this->posts->recentForDashboard(),
        );
    }
}
