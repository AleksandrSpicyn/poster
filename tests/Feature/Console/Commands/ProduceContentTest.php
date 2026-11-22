<?php

declare(strict_types=1);

use App\Enums\ProjectContentMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('плановые генерации по умолчанию выключены', function (): void {
    Bus::fake();
    Http::fake();
    serialOperator();
    Project::factory()->recycle(workspaceOwner())->create();

    consoleCommand('app:produce-content', ['--scheduled' => true])->assertSuccessful();

    expect(config('production.schedule_enabled'))->toBeFalse();
    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('выключенные проекты пропускаются и могут быть включены повторно', function (bool $scheduled): void {
    Bus::fake();
    $actor = serialOperator();
    config(['production.schedule_enabled' => true, 'services.admin.email' => $actor->email]);
    $active = Project::factory()->recycle(workspaceOwner())->create();
    $clipping = Project::factory()->recycle(workspaceOwner())->create(['is_active' => true, 'clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $disabled = Project::factory()->recycle(workspaceOwner())->create(['is_active' => false]);
    $serial = Project::factory()->recycle(workspaceOwner())->create(['is_active' => false, 'content_mode' => ProjectContentMode::SERIES]);
    Series::factory()->recycle(workspaceOwner())->for($serial)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);

    consoleCommand('app:produce-content', ['--scheduled' => $scheduled])->assertSuccessful();

    expect($active->productionRuns()->count())->toBe(1);
    expect($disabled->productionRuns()->exists())->toBeFalse();
    expect($clipping->productionRuns()->exists())->toBeFalse();
    expect($serial->productionRuns()->exists())->toBeFalse();
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();

    $disabled->update(['is_active' => true]);
    consoleCommand('app:produce-content', ['--project' => (string) $disabled->id])->assertSuccessful();

    expect($disabled->productionRuns()->count())->toBe(1);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 2);
})->with(['планировщик' => true, 'ручная команда' => false]);

test('один плановый запуск выбирает сериал или отдельный ролик без дублей', function (): void {
    Bus::fake();
    $actor = serialOperator();
    config(['production.schedule_enabled' => true, 'services.admin.email' => $actor->email]);
    $short = Project::factory()->recycle(workspaceOwner())->create();
    $serial = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($serial)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);

    consoleCommand('app:produce-content', ['--scheduled' => true])->assertSuccessful();
    consoleCommand('app:produce-content', ['--scheduled' => true])->assertSuccessful();

    expect(ProductionRun::query()->count())->toBe(2);
    expect($short->productionRuns()->sole()->series_id)->toBeNull();
    expect($serial->productionRuns()->sole()->series_id)->toBe($series->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 2);
});

test('проекты запускаются по местному времени один раз даже при переводе часов назад', function (): void {
    Bus::fake();
    $actor = serialOperator();
    config(['services.admin.email' => $actor->email, 'production.schedule_enabled' => true]);
    $clock = ['schedule_enabled' => true, 'schedule_timezone' => 'America/New_York', 'schedule_time' => '01:30'];
    $due = Project::factory()->recycle(workspaceOwner())->create($clock);
    $later = Project::factory()->recycle(workspaceOwner())->create([...$clock, 'schedule_timezone' => 'Asia/Bangkok']);
    $disabled = Project::factory()->recycle(workspaceOwner())->create([...$clock, 'schedule_enabled' => false]);
    $legacy = Project::factory()->recycle(workspaceOwner())->create();

    try {
        foreach (['2026-11-01T05:29:00Z', '2026-11-01T05:30:00Z', '2026-11-01T06:30:00Z'] as $instant) {
            Carbon::setTestNow($instant);
            consoleCommand('app:produce-content', ['--project-clock' => true])->assertSuccessful();
        }

        expect($due->productionRuns()->count())->toBe(1)
            ->and($later->productionRuns()->count())->toBe(0)
            ->and($disabled->productionRuns()->count())->toBe(0)
            ->and($legacy->productionRuns()->count())->toBe(0);

        consoleCommand('app:produce-content', ['--scheduled' => true])->assertSuccessful();

        expect($legacy->productionRuns()->count())->toBe(1)
            ->and($later->productionRuns()->count())->toBe(0);
        Bus::assertDispatchedTimes(AdvanceProductionJob::class, 2);
    } finally {
        Carbon::setTestNow();
    }
});
