<?php

declare(strict_types=1);

use App\Actions\Series\SaveSeriesAction;
use App\Data\Series\SeriesData;
use App\Data\Series\SeriesSpecificationData;
use App\Enums\ContentAudience;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

test('создает сериал с паспортом и общим проектом', function (): void {
    $project = Project::factory()->create();
    $data = new SeriesData('Forest stories', new SeriesSpecificationData('Two foxes explore a forest', audience: ContentAudience::CHILDREN));

    $series = app(SaveSeriesAction::class)->run($project, $data);

    expect($series->project_id)->toBe($project->id);
    expect($series->specification['audience'])->toBe('children');
    expect($series->specification_version)->toBe(1);
    expect($series->continuity_version)->toBe(0);
    expect($project->series()->firstOrFail()->id)->toBe($series->id);
});

test('защищает паспорт от устаревшей правки', function (): void {
    $project = Project::factory()->create();
    $action = app(SaveSeriesAction::class);
    $series = $action->run($project, new SeriesData('Forest', new SeriesSpecificationData('A forest')));
    $updated = $action->run($project, new SeriesData('Forest', new SeriesSpecificationData('A different forest'), 0), $series);

    expect(fn() => $action->run($project, new SeriesData('Old edit', new SeriesSpecificationData('Old'), 0), $series))
        ->toThrow(ValidationException::class);

    expect($updated->refresh()->specification_version)->toBe(2);
    expect($updated->lock_version)->toBe(1);
    expect($updated->name)->toBe('Forest');
});
