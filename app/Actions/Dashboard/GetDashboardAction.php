<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Data\ChannelObservationReportData;
use App\Data\DashboardData;
use App\Data\MetricPeriodData;
use App\Data\PublicationMetricsData;
use App\Models\User;
use App\Tasks\Dashboard\GetDashboard;
use App\Tasks\Dashboard\GetPublicationMetrics;
use Carbon\CarbonImmutable;

readonly class GetDashboardAction
{
    public function __construct(private GetDashboard $getDashboard, private GetPublicationMetrics $metrics) {}

    public function metrics(User $owner, MetricPeriodData $period, bool $includeProduction = false): PublicationMetricsData
    {
        $report = $this->metrics->run($owner, $period, $includeProduction);

        if ($period->channel === null || $includeProduction) {
            return $report;
        }

        $previousEnd = CarbonImmutable::parse($report->from, 'UTC');
        $days = (int) $previousEnd->diffInDays(CarbonImmutable::parse($report->to, 'UTC'));
        $previous = clone $period;
        $previous->to = $previousEnd->toDateString();
        $previous->from = $previousEnd->subDays($days - 1)->toDateString();

        $prior = $this->metrics->run($owner, $previous);
        $report->channelReport = new ChannelObservationReportData($report->observedTotals(), $previous->from, $previous->to, $report->observedChange(), $prior->observedChange(), $prior->hasObservations());

        return $report;
    }

    public function run(User $owner): DashboardData
    {
        return $this->getDashboard->run($owner);
    }
}
