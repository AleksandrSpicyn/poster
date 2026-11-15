<?php

declare(strict_types=1);

use App\Actions\Production\StartProductionAction;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Tests\Support\serialOperator;

test('повтор запроса и новый ключ не дублируют активный выпуск', function (): void {
    Bus::fake();
    Http::fake();
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $action = app(StartProductionAction::class);

    $first = $action->run($project, 'first', $actor, $series->id)->sole();
    $repeated = $action->run($project, 'first', $actor, $series->id)->sole();
    $overlapping = $action->run($project, 'second', $actor, $series->id)->sole();

    expect($first->status)->toBe(ProductionStatus::QUEUED);
    expect($repeated->id)->toBe($first->id);
    expect($overlapping->id)->toBe($first->id);
    expect(ProductionRun::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();
});

test('сериальный проект с выключенной автоматизацией не создаёт обычный ролик', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    Series::factory()->for($project)->create(['automation_enabled' => false]);

    $runs = app(StartProductionAction::class)->run($project, 'scheduled', $actor);

    expect($runs)->toBeEmpty();
    expect($project->prompts()->count())->toBe(0);
    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('обычный проект создаёт запуск без сериала', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->create();

    $run = app(StartProductionAction::class)->run($project, 'short', $actor)->sole();

    expect($run->project_id)->toBe($project->id);
    expect($run->series_id)->toBeNull();
    expect($run->actor_id)->toBe($actor->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
});

test('отозванные права не создают намерение выпуска', function (): void {
    Bus::fake();
    serialOperator();
    $project = Project::factory()->create();
    $actor = User::factory()->create();

    expect(fn() => app(StartProductionAction::class)->run($project, 'denied', $actor))
        ->toThrow(AuthorizationException::class);

    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});
