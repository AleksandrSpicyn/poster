<?php

declare(strict_types=1);

use App\Enums\ProjectContentMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Tests\Support\serialOperator;

test('выключенный проект не запускается вручную', function (ProjectContentMode $mode): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->create(['is_active' => false, 'content_mode' => $mode]);
    $series = $mode === ProjectContentMode::SERIES
        ? Series::factory()->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]) : null;

    actingAs($actor)->postJson(route('production_runs.store', $project), ['request_key' => 'disabled-project', 'series_id' => $series?->id])
        ->assertUnprocessable()->assertJsonValidationErrors('project');

    expect($project->productionRuns()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with([ProjectContentMode::SHORT, ProjectContentMode::SERIES]);

test('запускает сериал и сохраняет один выпуск при повторном запросе', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $payload = ['request_key' => 'http-production', 'series_id' => $series->id];

    actingAs($actor)->post(route('production_runs.store', $project), $payload)->assertRedirect();
    actingAs($actor)->post(route('production_runs.store', $project), $payload)->assertRedirect();

    $run = ProductionRun::query()->sole();
    expect($run->series_id)->toBe($series->id);
    expect($run->actor_id)->toBe($actor->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();
});

test('не запускает сериал другого проекта', function (): void {
    Bus::fake();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->create(['automation_enabled' => true]);

    actingAs(serialOperator())->postJson(route('production_runs.store', $project), [
        'request_key' => 'wrong-project', 'series_id' => $series->id,
    ])->assertNotFound();

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('не принимает пустой ключ выпуска', function (): void {
    Bus::fake();
    $project = Project::factory()->create();

    actingAs(serialOperator())->postJson(route('production_runs.store', $project), [])
        ->assertUnprocessable()->assertJsonValidationErrors('request_key');

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('посторонний пользователь не запускает производство', function (): void {
    Bus::fake();
    serialOperator();
    $project = Project::factory()->create();

    actingAs(User::factory()->create())->postJson(route('production_runs.store', $project), ['request_key' => 'denied'])
        ->assertForbidden();

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('гость не запускает производство', function (): void {
    Bus::fake();
    $project = Project::factory()->create();

    post(route('production_runs.store', $project), ['request_key' => 'guest'])->assertRedirect(route('login'));

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});
