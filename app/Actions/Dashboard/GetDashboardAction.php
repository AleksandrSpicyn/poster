<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Data\DashboardData;
use App\Data\MetricPeriodData;
use App\Data\PublicationMetricsData;
use App\Models\User;
use App\Tasks\Dashboard\GetDashboard;
use App\Tasks\Dashboard\GetPublicationMetrics;

readonly class GetDashboardAction
{
    public function __construct(private GetDashboard $getDashboard, private GetPublicationMetrics $metrics) {}

    public function metrics(User $owner, MetricPeriodData $period, bool $includeProduction = false): PublicationMetricsData
    {
        return $this->metrics->run($owner, $period, $includeProduction);
    }

    public function run(User $owner): DashboardData
    {
        return $this->getDashboard->run($owner);
    }
}
