<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetPublicationAnalyticsAction;
use App\Data\MetricPeriodData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Response;

final class PublicationAnalyticsController
{
    /**
     * Аналитика публикаций по выбранному периоду
     *
     * @group Кабинет
     * @authenticated
     */
    public function __invoke(#[CurrentUser] User $owner, MetricPeriodData $period, GetPublicationAnalyticsAction $analytics): Response
    {
        return inertia('PublicationAnalytics', ['analytics' => $analytics->run($owner, $period)]);
    }
}
