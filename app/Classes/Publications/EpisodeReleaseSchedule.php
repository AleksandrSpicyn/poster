<?php

declare(strict_types=1);

namespace App\Classes\Publications;

use Cron\CronExpression;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Carbon;

final readonly class EpisodeReleaseSchedule
{
    public function __construct(
        #[Config('production.release_cron')] private string $expression,
        #[Config('production.release_timezone')] private string $timezone,
        #[Config('production.release_window_minutes')] private int $window,
        #[Config('app.timezone')] private string $appTimezone,
    ) {}

    public function nextAfter(Carbon $after): Carbon
    {
        $cron = new CronExpression($this->expression);
        $slot = Carbon::instance($cron->getNextRunDate($after, timeZone: $this->timezone));

        if ($this->window > 0) {
            $next = $cron->getNextRunDate($slot, timeZone: $this->timezone);
            $maxOffset = min($this->window, (int) $slot->diffInMinutes($next) - 1);
            $slot->addMinutes(random_int(0, $maxOffset));
        }

        return $slot->setTimezone($this->appTimezone);
    }
}
