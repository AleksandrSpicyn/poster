<?php

declare(strict_types=1);

namespace App\Tasks\Dashboard;

use App\Data\DashboardData;
use App\Models\Post;
use App\Models\Prompt;

/** Читает общие данные кабинета без загрузки всей истории или вызова интеграций. */
class GetDashboard
{
    public function run(): DashboardData
    {
        $promptCount = Prompt::query()->count();
        $readyCount = Prompt::query()->where('generated', true)->count();

        return new DashboardData(
            promptCount: $promptCount,
            pendingCount: Prompt::query()->where('generated', false)->count(),
            readyCount: $readyCount,
            postCount: Post::query()->count(),
            recentPrompts: Prompt::query()->with('project', 'media')->latest()->latest('id')->limit(6)->get(),
            recentPosts: Post::query()->with('channel')->latest()->latest('id')->limit(5)->get(),
        );
    }
}
