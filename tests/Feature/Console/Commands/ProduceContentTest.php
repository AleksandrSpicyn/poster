<?php

declare(strict_types=1);

use App\Enums\ProjectContentMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Tests\Support\consoleCommand;
use function Tests\Support\serialOperator;

test('плановые генерации по умолчанию выключены', function (): void {
    Bus::fake();
    Http::fake();
    serialOperator();
    Project::factory()->create();

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
    $active = Project::factory()->create();
    $clipping = Project::factory()->create(['is_active' => true, 'clipping_workspace' => Project::OPUS_CLIP_WORKSPACE]);
    $disabled = Project::factory()->create(['is_active' => false]);
    $serial = Project::factory()->create(['is_active' => false, 'content_mode' => ProjectContentMode::SERIES]);
    Series::factory()->for($serial)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);

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
    $short = Project::factory()->create();
    $serial = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($serial)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);

    consoleCommand('app:produce-content', ['--scheduled' => true])->assertSuccessful();
    consoleCommand('app:produce-content', ['--scheduled' => true])->assertSuccessful();

    expect(ProductionRun::query()->count())->toBe(2);
    expect($short->productionRuns()->sole()->series_id)->toBeNull();
    expect($serial->productionRuns()->sole()->series_id)->toBe($series->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 2);
});
