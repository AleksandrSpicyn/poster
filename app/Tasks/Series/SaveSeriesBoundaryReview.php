<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\SaveBoundaryReviewData;
use App\Data\Series\SeriesAssemblyManifestData;
use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class SaveSeriesBoundaryReview
{
    public function __construct(private SeriesAssemblyRepository $assemblies, private SeriesRepository $series, private ProjectRepository $projects) {}

    public function run(SeriesAssembly $assembly, int $boundary, SaveBoundaryReviewData $data, User $actor): void
    {
        DB::transaction(function () use ($assembly, $boundary, $data, $actor): void {
            $context = $this->assemblies->withContext($assembly->id);
            $project = $this->projects->findLockedOrFail($context->series->project_id);
            $series = $this->series->findLockedOrFail($context->series_id);
            $current = $this->assemblies->findLockedOrFail($assembly->id);
            abort_unless($project->owner_id === $actor->id, 404);

            if ($current->status !== AssemblyStatus::READY || $series->release_started_at !== null || $series->archived_at !== null
                || $current->lock_version !== $data->lock_version) {
                throw ValidationException::withMessages(['review' => 'Состояние сборки изменилось. Обновите страницу перед сохранением проверки.']);
            }

            $parts = SeriesAssemblyManifestData::fromSnapshot($current->manifest)->parts;
            abort_unless($boundary > 0 && $boundary < count($parts) && $parts[$boundary - 1]['number'] === $boundary, 404);
            $review = $current->review ?? [];
            $boundaries = is_array($review['boundaries'] ?? null) ? $review['boundaries'] : [];
            $boundaries['after_' . $boundary] = ['notes' => $data->notes ?? '', 'reviewed' => $data->reviewed];
            $this->assemblies->update($current, ['review' => [...$review, 'boundaries' => $boundaries], 'lock_version' => $current->lock_version + 1]);
        });
    }
}
