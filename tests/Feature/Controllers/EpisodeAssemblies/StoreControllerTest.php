<?php

declare(strict_types=1);

use App\Jobs\BuildEpisodeAssemblyJob;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\episodeHttpFixture;
use function Tests\Support\workspaceOwner;

test('сборка сохраняет manifest выбранного дубля до FFmpeg job', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    $take = Prompt::factory()->recycle(workspaceOwner())->create(['project_id' => $series->project_id, 'shot_id' => $context['shot']->id, 'generated' => true, 'take_number' => 1, 'input_snapshot' => ['context_hash' => $context['revision']->context_hash, 'specification_version' => $series->specification_version, 'continuity_version' => $series->continuity_version]]);
    $take->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(Prompt::SHOT_VIDEO);
    Bus::fake();

    actingAs($context['user'])
        ->post(route('episodes.assemblies.store', $context['params']), ['shots' => [['prompt_id' => $take->id]]])
        ->assertRedirect();

    $assembly = $context['revision']->assemblies()->firstOrFail();
    expect($assembly->status->value)->toBe('queued');
    expect(data_get($assembly->manifest, 'shots.0.prompt_id'))->toBe($take->id);
    Bus::assertDispatched(BuildEpisodeAssemblyJob::class);
});

test('пустой монтаж не запускает сборку', function (): void {
    $context = episodeHttpFixture();
    Bus::fake();

    actingAs($context['user'])
        ->postJson(route('episodes.assemblies.store', $context['params']), ['shots' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('shots');

    expect($context['revision']->assemblies()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('устаревшее основание монтажа не меняет версии и очередь', function (): void {
    Storage::fake('local');
    $context = episodeHttpFixture();
    $series = $context['episode']->series()->firstOrFail();
    $take = Prompt::factory()->for($context['shot'])->create([
        'project_id' => $series->project_id, 'generated' => true, 'take_number' => 1,
        'input_snapshot' => ['context_hash' => $context['revision']->context_hash, 'specification_version' => $series->specification_version, 'continuity_version' => $series->continuity_version],
    ]);
    $take->addMedia(base_path('tests/Fixtures/portrait.png'))->preservingOriginal()->toMediaCollection(Prompt::SHOT_VIDEO);
    EpisodeAssembly::factory()->for($context['revision'], 'episodeRevision')->ready()->create();
    Bus::fake();

    actingAs($context['user'])
        ->postJson(route('episodes.assemblies.store', $context['params']), [
            'base_assembly_id' => 0, 'shots' => [['prompt_id' => $take->id]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('base_assembly_id');

    expect($context['revision']->assemblies()->count())->toBe(1);
    Bus::assertNothingDispatched();
});
