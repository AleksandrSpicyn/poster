<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\post;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\serialSeriesPayload;
use function Tests\Support\workspaceOwner;

test('один проект не получает второй сериал', function (): void {
    $context = serialCharacterContext();
    Bus::fake();

    actingAs(serialOperator())->postJson(route('series.store', $context['project']), serialSeriesPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('series');

    expect($context['project']->series()->sole()->id)->toBe($context['series']->id);
    Bus::assertNothingDispatched();
});

test('создание сохраняет паспорт без генерации и публикации', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    Process::fake();

    $response = actingAs(serialOperator())->post(route('series.store', $project), serialSeriesPayload());

    $series = Series::query()->firstOrFail();
    $response->assertRedirect(route('series.show', ['project' => $project, 'series' => $series]));
    expect($series->project_id)->toBe($project->id);
    expect($series->specification)->toEqual(serialSeriesPayload()['specification']);
    expect($series->specification_version)->toBe(1);
    Http::assertNothingSent();
    Process::assertNothingRan();
});

test('неполный паспорт не создаёт сериал', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(serialOperator())
        ->postJson(route('series.store', $project), ['name' => 'Story', 'specification' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('specification.premise');

    assertDatabaseCount('series', 0);
});

test('гость не создаёт сериал', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();

    post(route('series.store', $project), serialSeriesPayload())->assertRedirect(route('login'));

    assertDatabaseCount('series', 0);
});

test('подтверждённый посторонний пользователь не создаёт сериал', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    config(['services.admin.email' => 'operator@example.test', 'horizon.allowed_emails' => []]);

    actingAs(User::factory()->create(['email' => 'reader@example.test']))
        ->postJson(route('series.store', $project), serialSeriesPayload())
        ->assertNotFound();

    assertDatabaseCount('series', 0);
});

test('сохраняет выбранный режим звука и native для прежнего запроса', function (?string $mode, string $expected): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $payload = serialSeriesPayload();

    if ($mode === null) {
        unset($payload['specification']['audio_mode']);
    } else {
        $payload['specification']['audio_mode'] = $mode;
    }

    Bus::fake();
    Process::fake();

    actingAs(serialOperator())->post(route('series.store', $project), $payload)->assertRedirect();

    expect(Series::query()->firstOrFail()->specification['audio_mode'])->toBe($expected);
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
})->with(['прежний запрос' => [null, 'native'], 'немой сериал' => ['silent', 'silent']]);

test('неизвестный режим звука не сохраняет сериал и не запускает генерацию', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $payload = serialSeriesPayload();
    $payload['specification']['audio_mode'] = 'external-tts';
    Bus::fake();
    Process::fake();

    actingAs(serialOperator())
        ->postJson(route('series.store', $project), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('specification.audio_mode');

    assertDatabaseCount('series', 0);
    Bus::assertNothingDispatched();
    Process::assertNothingRan();
    Http::assertNothingSent();
});

test('паспорт не допускает длительность сверх лимита сценария', function (): void {
    $project = Project::factory()->recycle(workspaceOwner())->create();
    $payload = serialSeriesPayload();
    $payload['specification']['episode_duration_seconds'] = 301;
    Bus::fake();

    actingAs(serialOperator())
        ->postJson(route('series.store', $project), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('specification.episode_duration_seconds');

    assertDatabaseCount('series', 0);
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});
