<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Enums\ProductionStatus;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** @extends EloquentRepository<OpusClip> */
final readonly class OpusClipRepository extends EloquentRepository
{
    public function __construct(private ClippingAccountRepository $accounts) {}

    protected function model(): string
    {
        return OpusClip::class;
    }

    /** @param iterable<ImportedClipData> $clips */
    public function import(OpusSourceData $source, iterable $clips): Project
    {
        return DB::transaction(function () use ($source, $clips): Project {
            $project = Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->lockForUpdate()->first();

            if (! $project instanceof Project) {
                throw new RuntimeException('Select a Poster project with opus:configure before importing clips.');
            }

            $connectionId = $project->clipping_connection_id;

            if ($connectionId !== null) {
                $mapped = OpusSource::query()->where('clipping_connection_id', $connectionId)->where('external_id', $source->id)->first();

                if ($mapped !== null) {
                    $project = Project::query()->where('owner_id', $project->owner_id)->lockForUpdate()->findOrFail($mapped->project_id);
                }
            }

            $this->storeClips($project, $source, $clips, $connectionId, $project->clipping_workspace === Project::OPUS_CLIP_WORKSPACE);

            return $project;
        }, attempts: 3);
    }

    /** @param iterable<ImportedClipData> $clips */
    public function refreshSource(ClippingConnection $connection, int $sourceId, string $externalId, iterable $clips, ?string $expectedCheckpoint = null, ?string $nextCheckpoint = null): bool
    {
        return DB::transaction(function () use ($connection, $sourceId, $externalId, $clips, $expectedCheckpoint, $nextCheckpoint): bool {
            $origin = OpusSource::query()->whereKey($sourceId)->where('clipping_connection_id', $connection->id)
                ->where('external_id', $externalId)->where('publication_sync_checkpoint', $expectedCheckpoint)->whereNull('sync_paused_at')->lockForUpdate()->first();

            if ($origin === null) {
                return false;
            }

            $project = Project::query()->where('owner_id', $connection->owner_id)->where('clipping_connection_id', $connection->id)->lockForUpdate()->find($origin->project_id);

            if ($project === null) {
                return false;
            }

            $this->storeClips($project, new OpusSourceData($origin->external_id), $clips, $connection->id, false, $nextCheckpoint);

            return true;
        }, attempts: 3);
    }

    public function confirm(ClippingConnection $connection, int $previewId): Project
    {
        return DB::transaction(function () use ($connection, $previewId): Project {
            $connection = ClippingConnection::query()->where('owner_id', $connection->owner_id)->lockForUpdate()->findOrFail($connection->id);
            $preview = ClippingImport::query()->where('clipping_connection_id', $connection->id)->lockForUpdate()->findOrFail($previewId);
            $projects = Project::query()->where('owner_id', $connection->owner_id)->lockForUpdate();

            if ($preview->imported_at !== null) {
                return $projects->findOrFail($preview->project_id);
            }

            if (ClippingImport::query()->where('clipping_connection_id', $connection->id)->where('external_id', $preview->external_id)
                ->whereNotNull('imported_at')->where('id', '>', $previewId)->exists()) {
                throw ValidationException::withMessages(['project_id' => 'Для этого источника уже подтверждён более новый предпросмотр.']);
            }

            $origin = OpusSource::query()->where('clipping_connection_id', $connection->id)->where('external_id', $preview->external_id)->first();

            if ($origin !== null && $preview->project_id !== null && (int) $origin->project_id !== $preview->project_id) {
                throw ValidationException::withMessages(['project_id' => 'Сопоставление изменилось. Повторите предпросмотр.']);
            }

            $projectId = $preview->project_id ?? $origin?->project_id;
            $project = $projectId === null
                ? new Project(['name' => $preview->source_name ?? $preview->game ?? $preview->external_id, 'prompt' => '', 'is_active' => false])
                : $projects->whereNull('opus_source_id')->where('content_mode', 'short')->findOrFail($projectId);

            if ((($project->clipping_connection_id !== null || $project->clipping_workspace !== null) && $project->clipping_connection_id !== $connection->id)
                || ($project->exists && $project->productionRuns()->whereIn('status', ProductionStatus::blockingValues())->exists())) {
                throw ValidationException::withMessages(['project_id' => 'Проверьте подключение проекта и завершите активное производство.']);
            }

            $project->owner_id = $connection->owner_id;
            $project->clipping_connection_id = $connection->id;
            $project->save();

            $items = collect($preview->clips)->map(static fn(array $clip): ImportedClipData => new ImportedClipData(...$clip));
            $this->storeClips($project, new OpusSourceData($preview->external_id, $preview->source_name, $preview->game), $items, $connection->id, false);
            $this->accounts->attach($connection, $project, $preview);
            $preview->project_id = $project->id;
            $preview->imported_at = now();
            $preview->save();

            return $project;
        }, attempts: 3);
    }

    /** @param iterable<ImportedClipData> $clips */
    private function storeClips(Project $project, OpusSourceData $source, iterable $clips, ?int $connectionId, bool $legacyCards, ?string $nextCheckpoint = null): void
    {
        $origin = $project->opusSources()->firstOrNew(['external_id' => $source->id, 'clipping_connection_id' => $connectionId]);
        $oldName = $this->sourceProjectName($origin);

        if (filled($source->title)) {
            $origin->title = $source->title;
        }

        $origin->game = $source->game ?? $origin->game;
        $origin->publication_sync_checkpoint = $nextCheckpoint;

        if ($nextCheckpoint === null) {
            $origin->last_synced_at = now();
        }

        $origin->sync_error = null;
        $origin->sync_paused_at = null;
        $origin->save();

        $name = $this->sourceProjectName($origin);

        if ($legacyCards && $origin->wasRecentlyCreated) {
            $card = new Project(['name' => $name, 'prompt' => '', 'is_active' => $project->is_active]);
            $card->owner_id = $project->owner_id;
            $card->opus_source_id = $origin->id;
            $card->save();
        } elseif ($oldName !== $name) {
            Project::query()->where('opus_source_id', $origin->id)->where('owner_id', $project->owner_id)->where('name', $oldName)->update(['name' => $name]);
        }

        foreach ($clips as $clip) {
            $record = $this->query()->firstOrNew(['opus_source_id' => $origin->id, 'clip_id' => $clip->id]);
            $oldLinks = collect($record->publications ?? [])
                ->filter(fn(array $post): bool => $post['url'] !== null)
                ->whereNotIn('url', array_column($clip->publications, 'url'));

            $record->fill([
                'title' => $clip->title ?? $record->title,
                'duration_ms' => $clip->durationMs ?? $record->duration_ms,
                'score' => $clip->score ?? $record->score,
                'publications' => $nextCheckpoint !== null ? ($record->publications ?? []) : [...$clip->publications, ...$oldLinks->values()->all()],
            ])->save();
        }
    }

    private function sourceProjectName(OpusSource $source): string
    {
        $title = trim((string) $source->title);

        return mb_substr($title !== '' ? $title : ($source->game ?? 'OpusClip ' . $source->external_id), 0, 255);
    }

    /** @return LengthAwarePaginator<int, OpusClip> */
    public function paginate(Project $project, int $perPage, int $page): LengthAwarePaginator
    {
        $clips = $this->query();

        if ($project->opus_source_id !== null) {
            $clips->where('opus_source_id', $project->opus_source_id)->whereRelation('source.project', 'owner_id', $project->owner_id);
        } else {
            $clips->whereRelation('source', 'project_id', $project->id);
        }

        return $clips->with(['source','latestMetrics'])
            ->orderByDesc('id')->paginate($perPage, page: $page)->withQueryString();
    }

    /** @return LazyCollection<int, OpusClip> */
    public function forMetrics(Project $project): LazyCollection
    {
        return $this->query()->whereRelation('source', 'project_id', $project->id)->lazyById();
    }
}
