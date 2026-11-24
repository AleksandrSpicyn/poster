<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Repositories\OpusWorkspaceRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $hasClippingWorkspace = fn(): bool => app(OpusWorkspaceRepository::class)->project() !== null;

        if (filled(config('services.opus_clip.key'))) {
            $schedule->command('opus:sync')->hourly()->when($hasClippingWorkspace)->withoutOverlapping(60);
        }

        $schedule->command('clipping:refresh')->hourly()->withoutOverlapping(60);
        $schedule->command('opus:trends')->dailyAt('06:30')->timezone('Asia/Bangkok')->when($hasClippingWorkspace)->withoutOverlapping(120);
        $schedule->command('publications:metrics')->dailyAt('06:45')->timezone('Asia/Bangkok')->withoutOverlapping(120);
        $schedule->command('opus:metrics')->dailyAt('06:15')->timezone('Asia/Bangkok')->when($hasClippingWorkspace)->withoutOverlapping(120);

        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('billing:sync-checkouts')->everyMinute()->withoutOverlapping(30);

        $schedule->command('app:reconcile-episode-scripts --limit=50')->everyMinute()->withoutOverlapping(5);
        $schedule->command('app:reconcile-shot-generations --limit=50')->everyMinute()->withoutOverlapping(5);
        $schedule->command('app:reconcile-episode-assemblies --limit=50')->everyMinute()->withoutOverlapping(5);
        $schedule->command('app:reconcile-publications --limit=50')->everyMinute()->withoutOverlapping(5);
        $schedule->command('app:reconcile-productions --limit=50')->everyMinute()->withoutOverlapping(5);

        $schedule->command('app:produce-content --scheduled --project-clock')
            ->everyMinute()
            ->when(fn(): bool => (bool) config('production.schedule_enabled'))
            ->withoutOverlapping(5);

        $schedule->command('app:produce-content --scheduled')
            ->cron(config()->string('production.schedule_cron'))
            ->timezone(config()->string('production.schedule_timezone'))
            ->when(fn(): bool => (bool) config('production.schedule_enabled'))
            ->withoutOverlapping(5);
    })
    ->withBroadcasting(__DIR__ . '/../routes/channels.php', [
        'middleware' => ['web', 'auth', 'verified'],
        'as' => 'broadcasting.auth',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::using(6),
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['client_secret', 'api_key']);
    })->create();
