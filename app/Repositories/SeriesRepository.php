<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Series\GetSeriesListData;
use App\Data\Series\SeriesWorkspaceData;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStatus;
use App\Enums\SeriesOutputMode;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\Project;
use App\Models\Prompt;
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
            ->with('project.media', 'project.coverFrame')
            ->withCount(['characters', 'episodes', 'episodes as accepted_episodes_count' => static fn(Builder $query) => $query->whereNotNull('accepted_assembly_id')])
            ->when($data->active, static fn(Builder $query) => $query->whereNull('archived_at')->whereRelation('project', 'is_active', true))
            ->when($data->updated, static fn(Builder $query) => $query->latest('updated_at'))
            ->latest('id')
            ->paginate(20, page: $data->page)
            ->withQueryString();
    }

    public function workspace(Series $series, int $page): SeriesWorkspaceData
    {
        $series->loadMissing('project.media', 'project.coverFrame', 'latestProductionRun.episode')->loadCount(['characters' => static fn(Builder $query) => $query->whereNull('archived_at'), 'episodes']);
        $series->project?->loadMissing(['channels' => fn($query) => $query->where('channels.owner_id', $series->project?->owner_id)]);
        $series->latestProductionRun?->loadExists([
            'generationOperations as has_reviewed_take' => fn(Builder $query) => $query
                ->where('status', GenerationOperationStatus::SUCCEEDED)
                ->where('updated_at', '>=', $series->latestProductionRun?->updated_at)
                ->whereNotNull('result_snapshot->resolved_at')
                ->whereHas('prompt', fn(Builder $prompt) => $prompt->where('generated', true)),
            'generationOperations as has_late_take' => fn(Builder $query) => $query
                ->where('status', GenerationOperationStatus::CANCELLED)
                ->whereHas('prompt.media', fn(Builder $media) => $media->where('collection_name', Prompt::SHOT_VIDEO)),
        ]);

        return new SeriesWorkspaceData(
            series: $series,
            characters: $series->characters()
                ->whereNull('archived_at')
                ->with('currentRevision')
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(20, page: $page)
                ->withQueryString(),
            episodes: $series->episodes()->orderByDesc('number')->limit(20)->get(),
        );
    }

    /**
     * @param list<int> $ids
     * @return Collection<int, Series>
     */
    public function lockProductionTargets(Project $project, array $ids, bool $checkHistory): Collection
    {
        return $project->series()->whereKey($ids)->orderBy('id')->lockForUpdate()
            ->when($checkHistory, fn($query) => $query->withExists('productionRuns'))
            ->withExists(['productionRuns as final_production_completed' => fn(Builder $query) => $query
                ->where('status', ProductionStatus::COMPLETED)->whereColumn('production_runs.series_assembly_id', 'series.final_assembly_id')])
            ->with([
                'episodes' => fn($query) => $query->orderByDesc('number')->limit(1),
                'activeProductionRun' => fn($query) => $query->where('project_id', $project->id),
                'productionRuns' => fn($query) => $query->where('input_snapshot->output_mode', SeriesOutputMode::SINGLE_VIDEO->value)->oldest('id')->limit(1),
            ])->get();
    }

    public function hasAvailableCharacter(Series $series, int $id): bool
    {
        return $series->characters()->whereKey($id)->whereNull('archived_at')->whereNotNull('current_revision_id')->exists();
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
        $run = $series->productionRuns()->where('input_snapshot->output_mode', SeriesOutputMode::SINGLE_VIDEO->value)->oldest('id')->first();
        $ids = $run?->input_snapshot['channel_ids'] ?? [];

        return is_array($ids) ? array_values(array_filter($ids, is_int(...))) : [];
    }

    public function pauseCycle(?Series $series): void
    {
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
