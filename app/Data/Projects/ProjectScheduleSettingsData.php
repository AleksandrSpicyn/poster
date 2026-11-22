<?php

declare(strict_types=1);

namespace App\Data\Projects;

use App\Models\Project;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Spatie\LaravelData\Data;

final class ProjectScheduleSettingsData extends Data
{
    /** @param list<string> $timezones */
    public function __construct(
        public bool $configured,
        public bool $enabled,
        public string $time,
        public string $timezone,
        public ?string $next,
        public array $timezones,
    ) {}

    public static function forProject(Project $project): self
    {
        $timezone = $project->schedule_timezone ?? Config::string('production.schedule_timezone');
        $now = now($timezone);
        $time = $project->schedule_time;

        if ($time === null) {
            $next = new CronExpression(Config::string('production.schedule_cron'))->getNextRunDate($now, timeZone: $timezone);
            $time = $next->format('H:i');
        } else {
            $day = $now->copy()->startOfDay();
            do {
                $next = Carbon::parse($day->format('Y-m-d') . ' ' . $time, $timezone);
                $day->addDay();
            } while ($next <= $now || $next->format('H:i') !== $time);
        }

        $enabled = $project->schedule_enabled ?? true;
        $available = $enabled && $project->is_active && ! $project->isClippingProject() && config('production.schedule_enabled');

        return new self(
            $project->schedule_enabled !== null,
            $enabled,
            $time,
            $timezone,
            $available ? $next->format(DATE_ATOM) : null,
            DateTimeZone::listIdentifiers(),
        );
    }
}
