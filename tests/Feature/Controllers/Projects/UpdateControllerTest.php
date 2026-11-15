<?php

declare(strict_types=1);

use App\Actions\Shots\SubmitShotGenerationAction;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Scene;
use App\Models\Series;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\patch;
use function Tests\Support\invalidProjectInputs;
use function Tests\Support\projectPayload;
use function Tests\Support\serialOperator;
use function Tests\Support\seriesProjectPayload;

test('выключение проекта останавливает выпуск и сохраняет историю', function (ProjectContentMode $mode): void {
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => $mode]);
    $series = $mode === ProjectContentMode::SERIES
        ? Series::factory()->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]) : null;
    $prompt = Prompt::factory()->for($project)->create();
    $channel = Channel::factory()->for($project)->create();
    $run = ProductionRun::factory()->for($project)->create(['series_id' => $series?->id]);
    $payload = $series === null ? projectPayload() : [...seriesProjectPayload(), 'series_lock_version' => 0];
    Bus::fake();

    actingAs($actor)->patch(route('projects.update', $project), [...$payload, 'is_active' => false])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($project->refresh()->is_active)->toBeFalse();
    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($project->prompts()->sole()->id)->toBe($prompt->id);
    expect($project->channels()->sole()->id)->toBe($channel->id);
    expect($series?->refresh()->automation_enabled)->toBe($series !== null ? true : null);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
})->with([ProjectContentMode::SHORT, ProjectContentMode::SERIES]);

test('правка без флага не включает выключенный проект', function (): void {
    $project = Project::factory()->create(['is_active' => false]);

    actingAs(User::factory()->create())->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($project->refresh()->is_active)->toBeFalse();
});

test('флаг активности принимает только boolean', function (): void {
    $project = Project::factory()->create();

    actingAs(User::factory()->create())->patchJson(route('projects.update', $project), [...projectPayload(), 'is_active' => 'enabled'])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');

    expect($project->refresh()->is_active)->toBeTrue();
});

test('выключение автоматизации немедленно отменяет ещё не отправленное видео', function (): void {
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $episode = Episode::factory()->for($series)->create();
    $revision = EpisodeRevision::factory()->for($episode)->create();
    $scene = Scene::factory()->for($revision, 'episodeRevision')->create();
    $shot = Shot::factory()->for($scene)->create();
    $prompt = Prompt::factory()->for($project)->create(['shot_id' => $shot->id]);
    $operation = $prompt->generationOperations()->create([
        'stage' => GenerationOperationStage::VIDEO, 'status' => GenerationOperationStatus::PLANNED,
        'request_key' => 'pause-planned-video', 'idempotency_key' => 'pause-planned-video', 'request_snapshot' => [],
    ]);
    $run = ProductionRun::factory()->for($project)->for($series)->create(['episode_id' => $episode->id]);
    Bus::fake();
    Process::fake();

    actingAs($actor)->patch(route('projects.update', $project), [...seriesProjectPayload(),
        'series_lock_version' => 0, 'automation_enabled' => false])->assertRedirect()->assertSessionHasNoErrors();
    app(SubmitShotGenerationAction::class)->run($operation->id);

    $lateOperation = $prompt->generationOperations()->create([
        'production_run_id' => $run->id, 'stage' => GenerationOperationStage::IMAGE,
        'status' => GenerationOperationStatus::PLANNED, 'request_key' => 'pause-late-image',
        'idempotency_key' => 'pause-late-image', 'request_snapshot' => [],
    ]);
    app(SubmitShotGenerationAction::class)->run($lateOperation->id);

    expect($run->refresh()->status)->toBe(ProductionStatus::STOPPED);
    expect($operation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($operation->cancel_requested_at)->not->toBeNull();
    expect($lateOperation->refresh()->status)->toBe(GenerationOperationStatus::CANCELLED);
    expect($lateOperation->cancel_requested_at)->not->toBeNull();
    expect($series->refresh()->automation_enabled)->toBeFalse();
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('обычная правка настроек не запускает следующий выпуск вместо выключенного расписания', function (): void {
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    ProductionRun::factory()->for($project)->for($series)->create(['status' => ProductionStatus::COMPLETED]);
    Bus::fake();

    actingAs($actor)->patch(route('projects.update', $project), [...seriesProjectPayload(), 'series_lock_version' => 0])
        ->assertRedirect(route('projects.show', $project));

    expect($series->productionRuns()->count())->toBe(1);
    Bus::assertNothingDispatched();
});

test('настройки проекта изменяют существующий сериал и сохраняют эпизоды', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->for($project)->create();
    $episode = Episode::factory()->for($series)->create();
    $payload = [...seriesProjectPayload(), 'series_lock_version' => $series->lock_version];

    actingAs($actor)->patch(route('projects.update', $project), $payload)->assertRedirect(route('projects.show', $project));
    actingAs($actor)->patchJson(route('projects.update', $project), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('series_lock_version');

    expect($project->series()->count())->toBe(1);
    expect($series->refresh()->name)->toBe($project->refresh()->name);
    expect($series->automation_enabled)->toBeTrue();
    expect($series->episode_limit)->toBe(5);
    expect($series->episodes()->sole()->id)->toBe($episode->id);
    expect($series->productionRuns()->count())->toBe(1);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
});

test('проект с сериалом не переключается на одиночные видео', function (): void {
    $project = Project::factory()->create(['content_mode' => ProjectContentMode::SERIES]);
    Series::factory()->for($project)->create();
    Bus::fake();

    actingAs(serialOperator())->patchJson(route('projects.update', $project), [...projectPayload(), 'content_mode' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors('content_mode');

    expect($project->refresh()->content_mode)->toBe(ProjectContentMode::SERIES);
    Bus::assertNothingDispatched();
});

test('изменение сохраняет тему без потери истории и каналов', function (): void {
    $project = Project::factory()->create();
    $prompt = Prompt::factory()->for($project)->create();
    $channel = Channel::factory()->for($project)->create();

    actingAs(User::factory()->create())
        ->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect(route('projects.show', $project));

    assertDatabaseHas('projects', ['id' => $project->id, ...projectPayload()]);
    assertDatabaseHas('prompts', ['id' => $prompt->id, 'project_id' => $project->id, 'prompt' => $prompt->prompt]);
    assertDatabaseHas('channels', ['id' => $channel->id, 'project_id' => $project->id]);
    expect(Inertia::getFlashed())->toBe(['success' => 'Project updated.']);
});

test('ошибка изменения не меняет проект', function (array $invalid, string $field): void {
    $project = Project::factory()->create(['name' => 'Original', 'prompt' => 'Keep the original topic']);

    actingAs(User::factory()->create())
        ->patchJson(route('projects.update', $project), [...projectPayload(), ...$invalid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original', 'prompt' => 'Keep the original topic']);
})->with(invalidProjectInputs());

test('Inertia PATCH возвращает 303', function (): void {
    $project = Project::factory()->create();

    actingAs(User::factory()->create())
        ->patch(route('projects.update', $project), projectPayload(), ['X-Inertia' => 'true'])
        ->assertStatus(303)
        ->assertRedirect(route('projects.show', $project));
});

test('отсутствующий проект не обновляется', function (): void {
    actingAs(User::factory()->create())
        ->patch(route('projects.update', 99999), projectPayload())
        ->assertNotFound();
});

test('гость не меняет проект', function (): void {
    $project = Project::factory()->create(['name' => 'Original']);

    patch(route('projects.update', $project), projectPayload())->assertRedirect(route('login'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original']);
});

test('неподтверждённый пользователь не меняет проект', function (): void {
    $project = Project::factory()->create(['name' => 'Original']);

    actingAs(User::factory()->unverified()->create())
        ->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect(route('verification.notice'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original']);
});
