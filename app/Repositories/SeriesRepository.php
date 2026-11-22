<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Series\GetSeriesListData;
use App\Data\Series\SeriesWorkspaceData;
use App\Enums\SeriesOutputMode;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
    public function paginate(User $owner, GetSeriesListData $data): LengthAwarePaginator
    {
        return Series::query()->whereRelation('project', 'owner_id', $owner->id)
            ->with('project')
            ->withCount(['characters', 'episodes', 'episodes as accepted_episodes_count' => static fn(Builder $query) => $query->whereNotNull('accepted_assembly_id')])
            ->when($data->active, static fn(Builder $query) => $query->whereNull('archived_at')->whereRelation('project', 'is_active', true))
            ->when($data->updated, static fn(Builder $query) => $query->latest('updated_at'))
            ->latest('id')
            ->paginate(20, page: $data->page)
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

    /** @return list<int> */
    public function cycleChannelIds(Series $series): array
    {
        $run = $series->productionRuns()->whereNotNull('input_snapshot')->oldest('id')->first();
        $ids = $run?->input_snapshot['channel_ids'] ?? [];

        return is_array($ids) ? array_values(array_filter($ids, is_int(...))) : [];
    }

    public function pauseCycle(ProductionRun $run): void
    {
        $series = $run->series()->first();

        if ($series?->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
            $series->update(['cycle_paused_at' => now()]);
        }
    }

    public function hasEpisodes(Series $series): bool
    {
        return $series->episodes()->exists();
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
