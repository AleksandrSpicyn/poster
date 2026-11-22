<?php

declare(strict_types=1);

use App\Models\User;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Tests\Support\singleVideoFixture;

test('оператор видит финальный монтаж и точный состав частей', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);

    $first = $assembly->episodeAssemblies()->firstOrFail();

    actingAs($context['user'])->get(route('series_assemblies.show', $assembly))
        ->assertOk()->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('SeriesAssemblies/Show')
        ->where('parameters.can_build', true)->where('parameters.parts.0.ready', true)->where('parameters.parts.2.number', 3)
        ->where('assembly.id', $assembly->id)->where('assembly.source_versions.' . $first->id, $first->number)->has('assembly.source_versions', 3)->has('assembly.manifest.parts', 3)->where('release_started', false));
});

test('история показывает закреплённую версию при просмотре другой сборки', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    $pinned = $assembly->replicate();
    $pinned->number++;
    $pinned->manifest_hash = hash('sha256', 'published');
    $pinned->save();
    $context['series']->update(['final_assembly_id' => $pinned->id, 'release_started_at' => now()]);

    actingAs($context['user'])
        ->get(route('series_assemblies.show', $assembly))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('assembly.id', $assembly->id)
            ->where('series.final_assembly_id', $pinned->id)
            ->where('series.newer_assembly', null)
            ->where('release_started', true));
});

test('закреплённый выпуск сохраняет версию и показывает последний монтаж своего сериала', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    $newer = $assembly->replicate();
    $newer->number++;
    $newer->manifest_hash = hash('sha256', 'next-version');
    $newer->save();
    $context['series']->update(['final_assembly_id' => $assembly->id, 'release_started_at' => now()]);

    actingAs($context['user'])
        ->get(route('series_assemblies.show', $assembly))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('assembly.id', $assembly->id)
            ->where('series.newer_assembly.number', $newer->number)
            ->where('series.newer_assembly.status', $newer->status->value)
            ->has('project.cover')
            ->where('release_started', true));
});

test('непринятый эпизод занимает своё место и блокирует сборку всего цикла', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    $context['series']->episodes()->where('number', 2)->update(['accepted_assembly_id' => null]);

    actingAs($context['user'])
        ->get(route('series_assemblies.show', $assembly))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('parameters.can_build', false)
            ->where('parameters.parts.0.ready', true)
            ->where('parameters.parts.1.number', 2)
            ->where('parameters.parts.1.ready', false)
            ->where('parameters.parts.2.ready', true));
});

test('итоговый монтаж закрыт для гостя и пользователя без допуска', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);

    get(route('series_assemblies.show', $assembly))->assertRedirect(route('login'));
    actingAs(User::factory()->create())->get(route('series_assemblies.show', $assembly))->assertNotFound();
});
