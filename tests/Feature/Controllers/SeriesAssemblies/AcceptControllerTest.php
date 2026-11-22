<?php

declare(strict_types=1);

use App\Actions\Series\BuildSeriesAssemblyAction;
use App\Enums\AssemblyStatus;
use App\Tasks\Series\SaveSeriesAssembly;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Tests\Support\singleVideoFixture;

test('принимает единый ролик после проверки стыков без повторного продвижения истории', function (): void {
    Storage::fake('local');
    $context = singleVideoFixture();
    $assembly = app(SaveSeriesAssembly::class)->run($context['series']);
    app(BuildSeriesAssemblyAction::class)->run($assembly->id);
    $assembly->refresh();
    $review = ['lock_version' => $assembly->lock_version, 'boundaries_reviewed' => true, 'review_notes' => 'Проверено со звуком'];

    actingAs($context['user'])
        ->post(route('series_assemblies.accept', $assembly), $review)
        ->assertSessionHasErrors('boundaries_reviewed');

    foreach ([1, 2] as $number) {
        actingAs($context['user'])
            ->put(route('series_assemblies.boundaries.update', [$assembly, $number]), [
                'lock_version' => $assembly->refresh()->lock_version, 'reviewed' => true, 'notes' => 'Переход проверен',
            ])
            ->assertSessionHasNoErrors();
    }

    $assembly->refresh();
    $continuity = $context['series']->continuity_version;

    actingAs($context['user'])->post(route('series_assemblies.accept', $assembly), [
        'lock_version' => $assembly->lock_version, 'boundaries_reviewed' => true, 'review_notes' => 'Reviewed all boundaries with sound.',
    ])->assertRedirect();

    expect($assembly->refresh()->status)->toBe(AssemblyStatus::ACCEPTED);
    expect($context['series']->refresh()->final_assembly_id)->toBe($assembly->id);
    expect($context['series']->continuity_version)->toBe($continuity);
    expect($assembly->publications()->count())->toBe(0);
});
