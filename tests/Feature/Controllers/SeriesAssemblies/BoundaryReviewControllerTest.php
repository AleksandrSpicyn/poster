<?php

declare(strict_types=1);

use App\Enums\AssemblyStatus;
use App\Models\User;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\singleVideoFixture;

test('владелец сохраняет отдельные заметки и снимает отметку проверки', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    $assembly->update(['status' => AssemblyStatus::READY]);
    $assembly->refresh();

    $route = route('series_assemblies.boundaries.update', [$assembly, 1]);
    $input = ['lock_version' => $assembly->lock_version, 'reviewed' => true, 'notes' => 'Первый стык'];

    actingAs($context['user'])->put($route, $input)->assertRedirect(route('series_assemblies.show', $assembly));
    actingAs($context['user'])->put($route, [...$input, 'notes' => 'Устаревшая вкладка'])->assertSessionHasErrors('review');
    $assembly->refresh();
    expect(data_get($assembly->review, 'boundaries.after_1.notes'))->toBe('Первый стык');
    actingAs($context['user'])
        ->put(route('series_assemblies.boundaries.update', [$assembly, 2]), [...$input, 'lock_version' => $assembly->lock_version, 'notes' => 'Второй стык'])
        ->assertSessionHasNoErrors();
    actingAs($context['user'])
        ->put($route, [...$input, 'lock_version' => $assembly->refresh()->lock_version, 'reviewed' => false])
        ->assertSessionHasNoErrors();

    $assembly->refresh();
    expect(data_get($assembly->review, 'boundaries.after_1.reviewed'))->toBeFalse()
        ->and(data_get($assembly->review, 'boundaries.after_2.notes'))->toBe('Второй стык');
});

test('чужой пользователь и отсутствующий стык не изменяют проверку', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    $assembly->update(['status' => AssemblyStatus::READY]);
    $assembly->refresh();

    $input = ['lock_version' => $assembly->lock_version, 'reviewed' => true];

    actingAs(User::factory()->create())
        ->put(route('series_assemblies.boundaries.update', [$assembly, 1]), $input)
        ->assertNotFound();
    actingAs($context['user'])
        ->put(route('series_assemblies.boundaries.update', [$assembly, 3]), $input)
        ->assertNotFound();
    $assembly->update(['status' => AssemblyStatus::ACCEPTED]);
    actingAs($context['user'])
        ->put(route('series_assemblies.boundaries.update', [$assembly, 1]), $input)
        ->assertSessionHasErrors('review');

    expect($assembly->refresh()->review)->toBeNull();
});
