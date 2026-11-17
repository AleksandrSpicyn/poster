<?php

declare(strict_types=1);

use App\Data\Series\SeriesData;
use App\Enums\SeriesOutputMode;
use App\Models\Project;
use App\Tasks\Series\SaveSeries;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('единый ролик требует конечный ограниченный цикл', function (): void {
    $project = Project::factory()->create();
    $data = SeriesData::validateAndCreate(['name' => 'One movie', 'specification' => ['premise' => 'A story'], 'output_mode' => 'single_video']);

    expect(fn() => DB::transaction(fn() => app(SaveSeries::class)->run($project, $data)))->toThrow(ValidationException::class);
    expect($project->series()->exists())->toBeFalse();
});

test('способ выпуска сохраняется без изменения старого режима', function (): void {
    $project = Project::factory()->create();
    $data = SeriesData::validateAndCreate(['name' => 'One movie', 'specification' => ['premise' => 'A story'], 'output_mode' => 'single_video', 'episode_limit' => 3]);

    $series = DB::transaction(fn() => app(SaveSeries::class)->run($project, $data));

    expect($series->output_mode)->toBe(SeriesOutputMode::SINGLE_VIDEO);
    expect($series->episode_limit)->toBe(3);
    expect($series->specification['episode_duration_seconds'])->toBe(15);
});

test('начатый цикл разрешает выключить автоматизацию без изменения закреплённого паспорта', function (): void {
    $project = Project::factory()->create();
    $data = SeriesData::validateAndCreate(['name' => 'One movie', 'specification' => ['premise' => 'A story'], 'output_mode' => 'single_video', 'episode_limit' => 3]);
    $series = DB::transaction(fn() => app(SaveSeries::class)->run($project, $data));
    $series->update(['cycle_plan' => ['objective' => 'Pinned story']]);

    $current = $series->refresh();
    $edit = SeriesData::validateAndCreate(['name' => $current->name, 'specification' => $current->specification,
        'output_mode' => 'single_video', 'episode_limit' => 3, 'lock_version' => $current->lock_version]);

    $saved = DB::transaction(fn() => app(SaveSeries::class)->run($project, $edit, $current));

    expect($saved->specification)->toEqual($current->specification);
    expect($saved->automation_enabled)->toBeFalse();
});
