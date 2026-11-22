<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\EpisodePublishingContextData;
use App\Enums\AssemblyStatus;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** @extends EloquentRepository<EpisodeAssembly> */
readonly class EpisodeAssemblyRepository extends EloquentRepository
{
    protected function model(): string
    {
        return EpisodeAssembly::class;
    }

    /** @return Collection<int, EpisodeAssembly> */
    public function lockForReconciliation(int $limit): Collection
    {
        return EpisodeAssembly::query()->with('episodeRevision.episode.series.project')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $queued): void {
                    $queued->where('status', AssemblyStatus::QUEUED)->where('updated_at', '<=', now()->subSeconds(90));
                })->orWhere(function (Builder $building): void {
                    $building->where('status', AssemblyStatus::BUILDING)->where('started_at', '<=', now()->subSeconds(3720));
                });
            })
            ->orderBy('updated_at')
            ->limit(max(1, min(100, $limit)))
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param array<int, int> $ids
     * @return list<int>
     */
    public function acceptedSourceIds(array $ids, int $seriesId, int $episodeNumber): array
    {
        /** @var list<int> $accepted */
        $accepted = array_values(EpisodeAssembly::query()
            ->where('status', AssemblyStatus::ACCEPTED)
            ->whereHas('episodeRevision.episode', fn($query) => $query->where('series_id', $seriesId)->where('number', '<=', $episodeNumber))
            ->whereKey($ids)->pluck('id')->all());

        return $accepted;
    }

    public function latestUsableId(EpisodeRevision $revision): int
    {
        return $revision->assemblies()->where('status', '!=', AssemblyStatus::FAILED)->orderByDesc('id')->first(['id'])->id ?? 0;
    }

    public function matchingManifest(EpisodeRevision $revision, string $hash): ?EpisodeAssembly
    {
        return $revision->assemblies()->where('manifest_hash', $hash)
            ->whereIn('status', [AssemblyStatus::QUEUED, AssemblyStatus::BUILDING, AssemblyStatus::READY, AssemblyStatus::ACCEPTED])
            ->first();
    }

    /** @param array<string, mixed> $manifest */
    public function queueManifest(EpisodeRevision $revision, array $manifest, string $hash): EpisodeAssembly
    {
        return $revision->assemblies()->chaperone('episodeRevision')->create([
            'number' => ($revision->assemblies()->orderByDesc('number')->first()->number ?? 0) + 1,
            'status' => AssemblyStatus::QUEUED, 'manifest' => $manifest, 'manifest_hash' => $hash,
            'continuity_snapshot' => [],
        ])->refresh();
    }

    public function forContinuation(int $sourceAssemblyId): EpisodeAssembly
    {
        return EpisodeAssembly::query()->with('episodeRevision.episode', 'media')->findOrFail($sourceAssemblyId);
    }

    public function forExport(EpisodeAssembly $assembly): EpisodeAssembly
    {
        return $assembly->loadMissing('episodeRevision.episode');
    }

    public function forStoryInspection(EpisodeAssembly $assembly): EpisodeAssembly
    {
        return $assembly->load('episodeRevision.characterRevisions.character', 'episodeRevision.scenes.shots');
    }

    public function lockPublishingContext(EpisodeAssembly $assembly): EpisodePublishingContextData
    {
        $revision = EpisodeRevision::query()->findOrFail($assembly->episode_revision_id);
        $episode = Episode::query()->findOrFail($revision->episode_id);
        $series = Series::query()->findOrFail($episode->series_id);
        $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);
        $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);

        return new EpisodePublishingContextData($revision, $episode, $series, $project);
    }

    public function exists(int|string $id): bool
    {
        return EpisodeAssembly::query()->whereKey($id)->exists();
    }

    public function withMedia(int $id): EpisodeAssembly
    {
        return EpisodeAssembly::query()->with('media')->findOrFail($id);
    }

    public function lockedWithMedia(int $id): EpisodeAssembly
    {
        return EpisodeAssembly::query()->with('media')->lockForUpdate()->findOrFail($id);
    }

    public function loadMedia(EpisodeAssembly $assembly): EpisodeAssembly
    {
        return $assembly->loadMissing('media');
    }
}
