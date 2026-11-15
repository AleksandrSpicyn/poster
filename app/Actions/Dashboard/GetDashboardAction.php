<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Data\DashboardData;
use App\Tasks\Dashboard\GetDashboard;

readonly class GetDashboardAction
{
    public function __construct(private GetDashboard $getDashboard) {}

    public function run(): DashboardData
    {
        return $this->getDashboard->run();
    }
}
