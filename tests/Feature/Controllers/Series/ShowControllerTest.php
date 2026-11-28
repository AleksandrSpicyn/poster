<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ScriptGenerationStatus;
use App\Models\Character;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\serialCharacterContext;
use function Tests\Support\serialOperator;
use function Tests\Support\workspaceOwner;

test('карточка показывает только персонажей и эпизоды сериала', function (): void {
    $context = serialCharacterContext();
    Character::factory()->create(['name' => 'Foreign actor']);
    $episode = Episode::factory()->recycle(workspaceOwner())->for($context['series'])->create();
    Episode::factory()->recycle(workspaceOwner())->create(['title' => 'Foreign episode']);

    actingAs(serialOperator())
        ->get(route('series.show', $context['params']))
        ->assertOk()
        ->assertDontSee('Foreign actor')
        ->assertDontSee('Foreign episode')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('Series/Show')
            ->where('series.id', $context['series']->id)
            ->where('characters.data.0.id', $context['character']->id)
            ->has('characters.data', 1)
            ->where('episodes.0.id', $episode->id));
});

test('сериал скрыт в чужом проекте', function (): void {
    $context = serialCharacterContext();
    $other = Project::factory()->recycle(workspaceOwner())->create();

    actingAs(serialOperator())
        ->get(route('series.show', ['project' => $other, 'series' => $context['series']]))
        ->assertNotFound();
});

test('гость не видит карточку сериала', function (): void {
    $context = serialCharacterContext();

    get(route('series.show', $context['params']))->assertRedirect(route('login'));
});

test('карточка передаёт состояние сценария и принятую сборку своего эпизода', function (): void {
    $context = serialCharacterContext();
    $episode = Episode::factory()->for($context['series'])->create();
    $revision = EpisodeRevision::factory()->for($episode)->approved()->create();
    $assembly = EpisodeAssembly::factory()->ready()->create(['episode_revision_id' => $revision->id, 'status' => AssemblyStatus::ACCEPTED]);
    $episode->update([
        'current_revision_id' => $revision->id,
        'accepted_assembly_id' => $assembly->id,
        'script_generation_status' => ScriptGenerationStatus::COMPLETED,
    ]);

    actingAs(serialOperator())
        ->get(route('series.show', $context['params']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('episodes.0.current_revision_id', $revision->id)
            ->where('episodes.0.accepted_assembly_id', $assembly->id)
            ->where('episodes.0.script_generation_status', ScriptGenerationStatus::COMPLETED->value));
});

test('производство показывает ручную проверку только для готового дубля своего запуска', function (): void {
    $context = serialCharacterContext();
    $series = $context['series'];
    $episode = Episode::factory()->for($series)->create();
    $run = ProductionRun::factory()->for($series)->create([
        'project_id' => $series->project_id,
        'episode_id' => $episode->id,
        'status' => ProductionStatus::NEEDS_ATTENTION,
        'stage' => ProductionStage::MEDIA,
    ]);
    $prompt = Prompt::factory()->create(['project_id' => $series->project_id, 'generated' => false]);
    $prompt->generationOperations()->create([
        'production_run_id' => $run->id, 'stage' => GenerationOperationStage::VIDEO,
        'status' => GenerationOperationStatus::SUCCEEDED, 'request_key' => 'existing-take',
        'idempotency_key' => 'existing-take', 'request_snapshot' => [],
        'result_snapshot' => ['resolved_at' => now()->toISOString()],
    ]);

    actingAs(serialOperator())
        ->get(route('series.show', $context['params']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('series.latest_production.has_reviewed_take', false)
            ->where('series.latest_production.episode_title', $episode->title));

    $prompt->update(['generated' => true]);

    actingAs(serialOperator())
        ->get(route('series.show', $context['params']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('series.latest_production.has_reviewed_take', true)
            ->where('series.latest_production.has_late_take', false));
});

test('скрывает архивных персонажей до разбивки на страницы', function (): void {
    $context = serialCharacterContext();
    Character::factory()->count(20)->for($context['series'])->create(['archived_at' => now(), 'name' => 'AAA']);

    actingAs(serialOperator())
        ->get(route('series.show', $context['params']))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('characters.meta.total', 1)
            ->where('characters.data.0.id', $context['character']->id));

    actingAs(serialOperator())
        ->get(route('series.show', [...$context['params'], 'page' => 2, 'with_archived' => 1]))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('characters.meta.total', 1)
            ->has('characters.data', 0));
});

test('карточка показывает фактический режим отправки запуска', function (array $snapshot, bool $expected): void {
    $context = serialCharacterContext();
    $series = $context['series'];
    $series->update(['auto_publish' => true]);
    ProductionRun::factory()
        ->for($series)
        ->create(['project_id' => $series->project_id, 'input_snapshot' => $snapshot]);

    actingAs(serialOperator())
        ->get(route('series.show', [...$context['params'], 'section' => 'production']))
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('series.latest_production.automatic_delivery', $expected));
})->with([
    [['channel_ids' => [1]], true],
    [['channel_ids' => []], false],
    [['channel_ids' => [1], 'skip_delivery' => true], false],
    [['channel_ids' => null], true],
]);
