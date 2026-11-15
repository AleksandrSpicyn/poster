<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('app:reconcile-episode-scripts --limit=50')->everyMinute()->withoutOverlapping(5);
Schedule::command('app:reconcile-shot-generations --limit=50')->everyMinute()->withoutOverlapping(5);
Schedule::command('app:reconcile-episode-assemblies --limit=50')->everyMinute()->withoutOverlapping(5);
Schedule::command('app:reconcile-publications --limit=50')->everyMinute()->withoutOverlapping(5);
Schedule::command('app:reconcile-productions --limit=50')->everyMinute()->withoutOverlapping(5);

Schedule::command('app:produce-content --scheduled')
    ->cron(config()->string('production.schedule_cron'))
    ->timezone(config()->string('production.schedule_timezone'))
    ->when(fn(): bool => (bool) config('production.schedule_enabled'))
    ->withoutOverlapping(5);
