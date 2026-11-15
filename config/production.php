<?php

declare(strict_types=1);

return [
    'schedule_enabled' => (bool) env('CONTENT_SCHEDULE_ENABLED', false),
    'schedule_cron' => env('CONTENT_SCHEDULE_CRON', '0 9 * * *'),
    'schedule_timezone' => env('CONTENT_SCHEDULE_TIMEZONE', 'Asia/Bangkok'),
    'run_timeout_minutes' => 180,
    'character_settle_seconds' => 120,
    'frame_count' => 6,
];
