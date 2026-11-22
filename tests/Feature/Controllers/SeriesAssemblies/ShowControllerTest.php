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

    actingAs($context['user'])->get(route('series_assemblies.show', $assembly))
        ->assertOk()->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('SeriesAssemblies/Show')
        ->where('assembly.id', $assembly->id)->has('assembly.manifest.parts', 3)->where('release_started', false));
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
            ->where('release_started', true));
});

test('итоговый монтаж закрыт для гостя и пользователя без допуска', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);

    get(route('series_assemblies.show', $assembly))->assertRedirect(route('login'));
    actingAs(User::factory()->create())->get(route('series_assemblies.show', $assembly))->assertNotFound();
});
