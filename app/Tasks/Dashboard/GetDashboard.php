<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Data\DashboardData;
use App\Data\DashboardStatsData;
use App\Models\User;
use App\Repositories\PostRepository;
use App\Repositories\PromptRepository;
use Illuminate\Database\Eloquent\Collection;

/** Читает общие данные кабинета без загрузки всей истории или вызова интеграций. */
class GetDashboard
{
    public function __construct(private readonly PostRepository $posts, private readonly PromptRepository $prompts) {}

    public function run(User $owner): DashboardData
    {
        return new DashboardData(
            fn(): DashboardStatsData => $this->stats($owner),
            fn(): Collection => $this->prompts->recentForDashboard($owner),
            fn(): Collection => $this->posts->recentForDashboard($owner),
        );
    }

    private function stats(User $owner): DashboardStatsData
    {
        $counts = $this->prompts->dashboardCounts($owner);

        return new DashboardStatsData($counts['total'], $counts['total'] - $counts['ready'], $counts['ready'], $this->posts->totalCount($owner));
    }
}
