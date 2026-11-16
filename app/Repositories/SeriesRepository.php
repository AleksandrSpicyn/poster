<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Series\SeriesWorkspaceData;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** @extends EloquentRepository<Series> */
readonly class SeriesRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Series::class;
    }

    /** @return LengthAwarePaginator<int, Series> */
    public function paginate(int $page): LengthAwarePaginator
    {
        return Series::query()
            ->with('project')
            ->withCount('characters', 'episodes')
            ->latest('id')
            ->paginate(20, page: $page)
            ->withQueryString();
    }

    public function workspace(Series $series, int $page): SeriesWorkspaceData
    {
        $series->load('project', 'latestProductionRun')->loadCount('characters', 'episodes');

        return new SeriesWorkspaceData(
            series: $series,
            characters: $series->characters()
                ->with('currentRevision')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(20, page: $page)
                ->withQueryString(),
            episodes: $series->episodes()->orderByDesc('number')->limit(20)->get(),
        );
    }

    public function lockedForProject(int $projectId, int $id): Series
    {
        return Series::query()
            ->where('project_id', $projectId)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function refreshWithActiveRun(Series $series): Series
    {
        return $series->refresh()->load('activeProductionRun');
    }

    /** @return Collection<int, Series> */
    public function productionTargets(Project $project, ?int $seriesId): Collection
    {
        $series = $project->series()->with('automationUser')->whereNull('archived_at');

        if ($seriesId !== null) {
            $selected = $series->findOrFail($seriesId);

            return new Collection([$selected]);
        }

        return $series->where('automation_enabled', true)->orderBy('id')->get();
    }

    public function forEpisode(Episode $episode): Series
    {
        return $episode->series()->firstOrFail();
    }

    public function forAssembly(EpisodeAssembly $assembly): Series
    {
        return $assembly->episodeRevision()->firstOrFail()->episode()->firstOrFail()->series()->firstOrFail();
    }
}
