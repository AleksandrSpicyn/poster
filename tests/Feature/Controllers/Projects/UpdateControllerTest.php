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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\patch;
use function Tests\Support\invalidProjectInputs;
use function Tests\Support\projectPayload;
use function Tests\Support\serialOperator;
use function Tests\Support\seriesProjectPayload;
use function Tests\Support\workspaceOwner;

test('выключение проекта останавливает выпуск и сохраняет историю', function (ProjectContentMode $mode): void {
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => $mode]);
    $series = $mode === ProjectContentMode::SERIES
        ? Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]) : null;
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($project)->create();
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create();
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($project)->create(['series_id' => $series?->id]);
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
    $project = Project::factory()->recycle(workspaceOwner())->create(['is_active' => false]);

    actingAs(workspaceOwner())->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($project->refresh()->is_active)->toBeFalse();
});

test('флаг активности принимает только boolean', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())->patchJson(route('projects.update', $project), [...projectPayload(), 'is_active' => 'enabled'])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');

    expect($project->refresh()->is_active)->toBeTrue();
});

test('выключение автоматизации немедленно отменяет ещё не отправленное видео', function (): void {
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    $episode = Episode::factory()->recycle(workspaceOwner())->for($series)->create();
    $revision = EpisodeRevision::factory()->for($episode)->create();
    $scene = Scene::factory()->for($revision, 'episodeRevision')->create();
    $shot = Shot::factory()->for($scene)->create();
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($project)->create(['shot_id' => $shot->id]);
    $operation = $prompt->generationOperations()->create([
        'stage' => GenerationOperationStage::VIDEO, 'status' => GenerationOperationStatus::PLANNED,
        'request_key' => 'pause-planned-video', 'idempotency_key' => 'pause-planned-video', 'request_snapshot' => [],
    ]);
    $run = ProductionRun::factory()->recycle(workspaceOwner())->for($project)->for($series)->create(['episode_id' => $episode->id]);
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
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create(['automation_enabled' => true, 'automation_user_id' => $actor->id]);
    ProductionRun::factory()->recycle(workspaceOwner())->for($project)->for($series)->create(['status' => ProductionStatus::COMPLETED]);
    Bus::fake();

    actingAs($actor)->patch(route('projects.update', $project), [...seriesProjectPayload(), 'series_lock_version' => 0])
        ->assertRedirect(route('projects.show', $project));

    expect($series->productionRuns()->count())->toBe(1);
    Bus::assertNothingDispatched();
});

test('настройки проекта изменяют существующий сериал и сохраняют эпизоды', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    $series = Series::factory()->recycle(workspaceOwner())->for($project)->create();
    $episode = Episode::factory()->recycle(workspaceOwner())->for($series)->create();
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
    $project = Project::factory()->recycle(workspaceOwner())->create(['content_mode' => ProjectContentMode::SERIES]);
    Series::factory()->recycle(workspaceOwner())->for($project)->create();
    Bus::fake();

    actingAs(serialOperator())->patchJson(route('projects.update', $project), [...projectPayload(), 'content_mode' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors('content_mode');

    expect($project->refresh()->content_mode)->toBe(ProjectContentMode::SERIES);
    Bus::assertNothingDispatched();
});

test('изменение сохраняет тему без потери истории и каналов', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $prompt = Prompt::factory()->recycle(workspaceOwner())->for($project)->create();
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create();

    actingAs(workspaceOwner())
        ->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect(route('projects.show', $project));

    assertDatabaseHas('projects', ['id' => $project->id, ...projectPayload()]);
    assertDatabaseHas('prompts', ['id' => $prompt->id, 'project_id' => $project->id, 'prompt' => $prompt->prompt]);
    assertDatabaseHas('channels', ['id' => $channel->id, 'project_id' => $project->id]);
    expect(Inertia::getFlashed())->toBe(['success' => 'Project updated.']);
});

test('ошибка изменения не меняет проект', function (array $invalid, string $field): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['name' => 'Original', 'prompt' => 'Keep the original topic']);

    actingAs(workspaceOwner())
        ->patchJson(route('projects.update', $project), [...projectPayload(), ...$invalid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original', 'prompt' => 'Keep the original topic']);
})->with(invalidProjectInputs());

test('Inertia PATCH возвращает 303', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(workspaceOwner())
        ->patch(route('projects.update', $project), projectPayload(), ['X-Inertia' => 'true'])
        ->assertStatus(303)
        ->assertRedirect(route('projects.show', $project));
});

test('отсутствующий проект не обновляется', function (): void {
    actingAs(workspaceOwner())
        ->patch(route('projects.update', 99999), projectPayload())
        ->assertNotFound();
});

test('гость не меняет проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['name' => 'Original']);

    patch(route('projects.update', $project), projectPayload())->assertRedirect(route('login'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original']);
});

test('неподтверждённый пользователь не меняет проект', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['name' => 'Original']);

    actingAs(User::factory()->unverified()->create())
        ->patch(route('projects.update', $project), projectPayload())
        ->assertRedirect(route('verification.notice'));

    assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Original']);
});

test('галочки заменяют связи без копирования каналов и токенов', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $old = Channel::factory()->recycle(workspaceOwner())->for($project)->create();
    $shared = Channel::factory()->recycle(workspaceOwner())->create(['credentials' => ['access_token' => 'shared-token']]);
    $otherProjectIds = $shared->projects()->allRelatedIds()->all();

    actingAs(serialOperator())->patch(route('projects.update', $project), [...projectPayload(), 'channel_ids' => [$shared->id]])
        ->assertSessionHasNoErrors();

    expect($project->channels()->sole()->id)->toBe($shared->id);
    expect($shared->refresh()->credentials)->toBe(['access_token' => 'shared-token']);
    expect($shared->projects()->count())->toBe(count($otherProjectIds) + 1);
    expect($old->fresh())->not->toBeNull();
    assertDatabaseCount('channels', 2);
    Http::assertNothingSent();

    actingAs(serialOperator())->patch(route('projects.update', $project), [...projectPayload(), 'channel_ids' => []])->assertSessionHasNoErrors();

    expect($project->channels()->exists())->toBeFalse();
    expect($shared->projects()->allRelatedIds()->all())->toBe($otherProjectIds);
});

test('отсутствие галочек в запросе сохраняет подключения', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create();

    actingAs(workspaceOwner())->patch(route('projects.update', $project), projectPayload())->assertSessionHasNoErrors();

    expect($project->channels()->sole()->id)->toBe($channel->id);
});

test('обычный пользователь не меняет подключения', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['name' => 'Original']);
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create();

    actingAs(User::factory()->create())->patchJson(route('projects.update', $project), [...projectPayload(), 'channel_ids' => []])->assertNotFound();

    expect($project->channels()->sole()->id)->toBe($channel->id);
    expect($project->refresh()->name)->toBe('Original');
});

test('неверный выбор каналов не меняет проект', function (array $ids): void {
    $project = Project::factory()->recycle(workspaceOwner())->create(['name' => 'Original']);
    $channel = Channel::factory()->recycle(workspaceOwner())->for($project)->create();

    actingAs(serialOperator())->patchJson(route('projects.update', $project), [...projectPayload(), 'channel_ids' => $ids])->assertUnprocessable();

    expect($project->channels()->sole()->id)->toBe($channel->id);
    expect($project->refresh()->name)->toBe('Original');
})->with([
    'несуществующий канал' => [[999999]],
    'дубликат' => [[1, 1]],
    'boolean' => [[true]],
    'массив' => [[['id' => 1]]],
    'объект вместо списка' => [['first' => 1]],
]);

test('устаревший черновик не перезаписывает настройки проекта', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $url = route('projects.update', $project);
    $fields = ['prompt' => 'Новый сюжет', 'settings_version' => 1];

    actingAs($owner)->patch($url, ['name' => 'Сезон 2', ...$fields])->assertSessionHasNoErrors();
    actingAs($owner)->patch($url, ['name' => 'Север', ...$fields])->assertSessionHasErrors('settings_version');

    expect($project->refresh()->name)->toBe('Сезон 2')
        ->and($project->settings_version)->toBe(2);
});

test('сохраняет выбранные каналы одной пакетной вставкой', function (int $size): void {
    $owner = serialOperator();
    $project = Project::factory()->for($owner, 'owner')->create();
    $channels = Channel::factory()->count($size)->create(['owner_id' => $owner->id, 'project_id' => null]);
    $removed = Channel::factory()->for($project)->create();

    DB::enableQueryLog();

    try {
        actingAs($owner)
            ->patch(route('projects.update', $project), [...projectPayload(), 'channel_ids' => $channels->modelKeys()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $inserts = collect(DB::getQueryLog())->pluck('query')->filter(fn(mixed $sql): bool => is_string($sql) && preg_match('/^insert into [`"]channel_project[`"]/i', $sql) === 1);
    } finally {
        DB::disableQueryLog();
    }

    expect($inserts)->toHaveCount(1)
        ->and($project->channels()->count())->toBe($size)
        ->and($project->channels()->whereKey($removed->id)->exists())->toBeFalse();
})->with([1, 10, 100]);

test('владелец сохраняет расписание проекта отдельно от остальных настроек', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $other = Project::factory()->recycle(workspaceOwner())->create();
    $schedule = ['enabled' => true, 'time' => '08:35', 'timezone' => 'Asia/Tokyo'];

    actingAs(workspaceOwner())->patch(route('projects.update', $project), [...projectPayload(), 'schedule' => $schedule])
        ->assertSessionHasNoErrors();

    actingAs(workspaceOwner())->patch(route('projects.update', $project), projectPayload())
        ->assertSessionHasNoErrors();

    expect($project->refresh()->schedule_time)->toBe('08:35')
        ->and($project->schedule_timezone)->toBe('Asia/Tokyo')
        ->and($project->schedule_enabled)->toBeTrue()
        ->and($other->refresh()->schedule_enabled)->toBeNull();

    actingAs(workspaceOwner())->patchJson(route('projects.update', $project), [...projectPayload(), 'schedule' => [...$schedule, 'time' => '26:17', 'timezone' => 'somewhere']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule.time', 'schedule.timezone']);

    expect($project->refresh()->schedule_time)->toBe('08:35');
});
