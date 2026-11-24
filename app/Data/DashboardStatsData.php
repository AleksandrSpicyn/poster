<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class DashboardStatsData extends Data
{
    public function __construct(public int $prompts, public int $pending, public int $ready, public int $posts) {}
}
