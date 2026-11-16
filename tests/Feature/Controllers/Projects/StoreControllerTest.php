<?php

declare(strict_types=1);

use App\Enums\ChannelType;
use App\Enums\ProjectContentMode;
use App\Jobs\AdvanceProductionJob;
use App\Models\Channel;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\post;
use function Tests\Support\invalidProjectInputs;
use function Tests\Support\projectPayload;
use function Tests\Support\serialOperator;
use function Tests\Support\seriesProjectPayload;

test('выключенный сериальный проект сохраняет настройки без запуска выпуска', function (): void {
    Bus::fake();

    actingAs(serialOperator())->post(route('projects.store'), [...seriesProjectPayload(), 'is_active' => false])
        ->assertRedirect()->assertSessionHasNoErrors();

    $project = Project::query()->sole();
    expect($project->is_active)->toBeFalse();
    expect($project->series()->sole()->automation_enabled)->toBeTrue();
    expect($project->productionRuns()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

test('проект сериала сохраняет настройки и начинает выпуск одним запросом', function (): void {
    Bus::fake();
    $actor = serialOperator();
    $source = Project::factory()->create();
    $channel = Channel::factory()->for($source)->create(['type' => ChannelType::YOUTUBE, 'credentials' => ['access_token' => 'private-token']]);

    actingAs($actor)->post(route('projects.store'), [...seriesProjectPayload(), 'channel_ids' => [$channel->id]])
        ->assertRedirect();

    $project = Project::query()->whereKeyNot($source->id)->sole();
    $series = $project->series()->sole();
    expect($project->content_mode)->toBe(ProjectContentMode::SERIES);
    expect($series->name)->toBe($project->name);
    expect($series->episode_limit)->toBe(5);
    expect($series->specification['story_outline'])->toBe('Five small adventures with a clear ending in the last episode.');
    expect($series->automation_user_id)->toBe($actor->id);
    expect($series->episodes()->exists())->toBeFalse();
    expect($project->channels()->sole()->id)->toBe($channel->id);
    expect($channel->refresh()->projects()->count())->toBe(2);
    expect($channel->refresh()->project_id)->toBe($source->id);
    expect($series->productionRuns()->sole()->actor_id)->toBe($actor->id);
    Bus::assertDispatchedTimes(AdvanceProductionJob::class, 1);
    Http::assertNothingSent();
});

test('пустые необязательные поля сериала не мешают созданию проекта', function (): void {
    Bus::fake();
    $payload = seriesProjectPayload();
    $payload['automation_enabled'] = false;
    $payload['series_specification']['narrative_rules'] = '';
    $payload['series_specification']['story_outline'] = '';

    actingAs(serialOperator())->post(route('projects.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

    $series = Series::query()->sole();
    expect($series->specification['narrative_rules'])->toBe('');
    expect($series->specification['story_outline'] ?? null)->toBeNull();
    Bus::assertNothingDispatched();
});

test('проект сериала требует настройки до сохранения', function (): void {
    Bus::fake();

    actingAs(serialOperator())->postJson(route('projects.store'), [...projectPayload(), 'content_mode' => 'series'])
        ->assertUnprocessable()->assertJsonValidationErrors('series_specification');

    expect(Project::query()->exists())->toBeFalse();
    expect(Series::query()->exists())->toBeFalse();
    expect(ProductionRun::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('читатель не создаёт сериал через обычную форму проекта', function (): void {
    serialOperator();
    Bus::fake();

    actingAs(User::factory()->create())->postJson(route('projects.store'), seriesProjectPayload())->assertForbidden();

    expect(Project::query()->exists())->toBeFalse();
    expect(Series::query()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
});

test('создание сохраняет проект и не запускает генерацию', function (): void {
    $user = User::factory()->create();
    Process::fake();
    Http::fake();

    $response = actingAs($user)->post(route('projects.store'), [
        ...projectPayload(), 'id' => 99999, 'credentials' => ['secret' => 'ignored'],
    ]);

    $project = Project::query()->firstOrFail();
    $response->assertRedirect(route('projects.show', $project));
    assertDatabaseCount('projects', 1);
    assertDatabaseHas('projects', projectPayload());
    expect($project->id)->not->toBe(99999);
    expect(Inertia::getFlashed())->toBe(['success' => 'Project created.']);
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('создание отклоняет некорректные поля', function (array $invalid, string $field): void {
    actingAs(User::factory()->create())
        ->postJson(route('projects.store'), [...projectPayload(), ...$invalid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    assertDatabaseCount('projects', 0);
})->with(invalidProjectInputs());

test('создание принимает предельные длины', function (): void {
    actingAs(User::factory()->create())
        ->post(route('projects.store'), ['name' => str_repeat('Ж', 255), 'prompt' => str_repeat('я', 10000)])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    assertDatabaseHas('projects', ['name' => str_repeat('Ж', 255), 'prompt' => str_repeat('я', 10000)]);
});

test('ошибка формы сохраняет проект несозданным', function (): void {
    actingAs(User::factory()->create())
        ->from(route('projects.create'))
        ->post(route('projects.store'), ['name' => 'Series'])
        ->assertRedirect(route('projects.create'))
        ->assertSessionHasErrors('prompt');

    assertDatabaseCount('projects', 0);
});

test('гость не создаёт проект', function (): void {
    post(route('projects.store'), projectPayload())->assertRedirect(route('login'));

    assertDatabaseCount('projects', 0);
});

test('неподтверждённый пользователь не создаёт проект', function (): void {
    actingAs(User::factory()->unverified()->create())
        ->post(route('projects.store'), projectPayload())
        ->assertRedirect(route('verification.notice'));

    assertDatabaseCount('projects', 0);
});
