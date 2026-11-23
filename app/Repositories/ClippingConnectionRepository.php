<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\ClippingAccessData;
use App\Data\ClippingConnectionData;
use App\Data\ClippingImportData;
use App\Data\ClippingScopeData;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\OpusClip;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

final readonly class ClippingConnectionRepository
{
    public function __construct(private ClippingSyncRunRepository $runs) {}

    /** @return Collection<int, ClippingConnection> */
    public function owned(User $owner): Collection
    {
        return ClippingConnection::query()->where('owner_id', $owner->id)->orderBy('name')->get(['id', 'name', 'driver', 'access_result', 'catalog_checked_at', 'catalog_error', 'import_projects']);
    }

    /** @return LazyCollection<int, OpusSource> */
    public function scheduledSources(): LazyCollection
    {
        return OpusSource::query()->whereNull('sync_paused_at')
            ->whereIn('clipping_connection_id', ClippingConnection::query()->where('uses_server_key', false)->where('import_projects', true)->select('id'))->lazyById();
    }

    /** @return LazyCollection<int, ClippingConnection> */
    public function scheduledConnections(): LazyCollection
    {
        return ClippingConnection::query()->where('uses_server_key', false)->where('import_projects', true)->lazyById();
    }

    public function recordDiscovery(ClippingConnection $connection, ?string $error): void
    {
        ClippingConnection::query()->whereKey($connection->id)->where('scope_version', $connection->scope_version)
            ->update(['catalog_checked_at' => now(), 'catalog_error' => $error]);
    }

    /** @return Collection<string, OpusSource> */
    public function catalogSources(ClippingConnection $connection): Collection
    {
        return OpusSource::query()->where('clipping_connection_id', $connection->id)
            ->get(['id', 'external_id', 'sync_paused_at'])->keyBy('external_id');
    }

    public function discover(ClippingConnection $connection, OpusSourceData $candidate): ?OpusSource
    {
        return DB::transaction(function () use ($connection, $candidate): ?OpusSource {
            $current = ClippingConnection::query()->where('scope_version', $connection->scope_version)->where('import_projects', true)->lockForUpdate()->find($connection->id);

            if ($current === null) {
                return null;
            }

            $source = OpusSource::query()->where('clipping_connection_id', $current->id)->where('external_id', $candidate->id)->first();

            if ($source !== null) {
                return $source;
            }

            $project = new Project(['name' => filled($candidate->title) ? $candidate->title : $candidate->id, 'prompt' => '', 'is_active' => false]);
            $project->owner_id = $current->owner_id;
            $project->clipping_connection_id = $current->id;
            $project->save();

            return $project->opusSources()->create(['clipping_connection_id' => $current->id, 'external_id' => $candidate->id, 'title' => $candidate->title, 'game' => $candidate->game]);
        }, attempts: 3);
    }

    public function find(int $id): ?ClippingConnection
    {
        return ClippingConnection::query()->find($id);
    }

    public function syncSource(ClippingConnection $connection, int $id, string $externalId): ?OpusSource
    {
        return OpusSource::query()->where('clipping_connection_id', $connection->id)->where('external_id', $externalId)
            ->whereRelation('project', 'owner_id', $connection->owner_id)->whereRelation('project', 'clipping_connection_id', $connection->id)
            ->whereNull('sync_paused_at')->find($id);
    }

    public function resume(ClippingConnection $connection, int $id): ?OpusSource
    {
        return DB::transaction(function () use ($connection, $id): ?OpusSource {
            $source = OpusSource::query()->where('clipping_connection_id', $connection->id)
                ->whereRelation('project', 'owner_id', $connection->owner_id)->whereRelation('project', 'clipping_connection_id', $connection->id)
                ->lockForUpdate()->findOrFail($id);

            if ($source->sync_paused_at === null) {
                return null;
            }

            $this->runs->open($source)->forceFill(['resumed_at' => now()])->save();
            $source->forceFill(['sync_paused_at' => null, 'sync_error' => null])->save();

            return $source;
        }, attempts: 3);
    }

    public function markSyncError(ClippingConnection $connection, int $sourceId, ?string $checkpoint, string $message, bool $pause = false): void
    {
        DB::transaction(function () use ($connection, $sourceId, $checkpoint, $message, $pause): void {
            $current = ClippingConnection::query()->where('owner_id', $connection->owner_id)->where('import_projects', true)
                ->where('scope_version', $connection->scope_version)->lockForUpdate()->find($connection->id, ['id']);

            if ($current === null) {
                return;
            }

            $source = OpusSource::query()->whereKey($sourceId)->where('clipping_connection_id', $connection->id)
                ->whereRelation('project', 'owner_id', $connection->owner_id)->whereRelation('project', 'clipping_connection_id', $connection->id)
                ->where('publication_sync_checkpoint', $checkpoint)->lockForUpdate()->first();

            if ($source !== null) {
                $source->forceFill(['sync_error' => $message, ...($pause ? ['sync_paused_at' => now()] : [])])->save();
                $this->runs->open($source)->forceFill(['failed_at' => now()])->save();
            }
        }, attempts: 3);
    }

    /** @return Collection<int, OpusSource>|null */
    public function accessSources(ClippingConnection $connection, ClippingAccessData $data): ?Collection
    {
        if ($data->project_id === null && $data->source_id === null) {
            return null;
        }

        $sources = OpusSource::query()->where('clipping_connection_id', $connection->id)
            ->whereRelation('project', 'owner_id', $connection->owner_id)->whereRelation('project', 'clipping_connection_id', $connection->id);

        if ($data->project_id !== null) {
            Project::query()->where('clipping_connection_id', $connection->id)->where('owner_id', $connection->owner_id)->findOrFail($data->project_id, ['id']);
            $sources->where('project_id', $data->project_id);
        }

        if ($data->source_id !== null) {
            return new Collection([$sources->findOrFail($data->source_id)]);
        }

        return $sources->get(['project_id', 'external_id']);
    }

    /** @return Collection<int, Project> */
    public function linkedProjects(ClippingConnection $connection): Collection
    {
        return Project::query()->where('owner_id', $connection->owner_id)->where('clipping_connection_id', $connection->id)
            ->whereNull('opus_source_id')->where('content_mode', 'short')->with('media', 'coverFrame')->select(['projects.*'])->addSelect(['clips_count' => OpusClip::query()
            ->selectRaw('count(*)')->join('opus_sources', 'opus_sources.id', '=', 'opus_clips.opus_source_id')
            ->whereColumn('opus_sources.project_id', 'projects.id')->whereColumn('opus_sources.clipping_connection_id', 'projects.clipping_connection_id')])
            ->orderBy('name')->get();
    }

    public function updateScope(ClippingConnection $connection, ClippingScopeData $data): void
    {
        DB::transaction(function () use ($connection, $data): void {
            $locked = ClippingConnection::query()->where('owner_id', $connection->owner_id)->lockForUpdate()->findOrFail($connection->id);
            $locked->import_projects = $data->import_projects;
            $locked->import_accounts = $data->import_accounts;
            $locked->import_archives = $data->import_archives;

            if ($locked->isDirty()) {
                $locked->forceFill(['access_result' => null, 'access_checked_at' => null]);
                $locked->scope_version++;
                $locked->save();
            }
        }, attempts: 3);
    }

    /** @param array<string, mixed> $result */
    public function recordAccess(ClippingConnection $connection, array $result): void
    {
        $current = ClippingConnection::query()->whereKey($connection->id)->where('owner_id', $connection->owner_id)->where('scope_version', $connection->scope_version);
        $stored = $current->update(['access_result' => json_encode($result, JSON_THROW_ON_ERROR), 'access_checked_at' => now()]);

        if ($stored === 0 && ! $current->exists()) {
            throw ValidationException::withMessages(['connection' => 'Подключение изменилось. Повторите проверку.']);
        }
    }

    public function updateKey(ClippingConnection $connection, string $key): void
    {
        DB::transaction(function () use ($connection, $key): void {
            $locked = ClippingConnection::query()->where('owner_id', $connection->owner_id)->lockForUpdate()->findOrFail($connection->id);
            OpusSource::query()->where('clipping_connection_id', $locked->id)->whereIn('sync_error', ['HTTP 401', 'HTTP 403'])
                ->update(['sync_paused_at' => null, 'sync_error' => null]);
            $locked->forceFill(['catalog_checked_at' => null, 'catalog_error' => null]);
            $locked->api_key = $key;
            $locked->uses_server_key = false;
            $locked->forceFill(['access_result' => null, 'access_checked_at' => null]);
            $locked->scope_version++;
            $locked->save();
        }, attempts: 3);
    }

    public function save(User $owner, ClippingConnectionData $data): ClippingConnection
    {
        $connection = new ClippingConnection(['name' => $data->name, 'driver' => $data->driver, 'api_key' => $data->api_key]);
        $connection->owner_id = $owner->id;
        $connection->save();

        return $connection;
    }

    public function availableProject(ClippingConnection $connection, int $id): ?Project
    {
        return $this->availableProjects($connection)->find($id, ['id', 'name']);
    }

    /** @return Builder<Project> */
    private function availableProjects(ClippingConnection $connection): Builder
    {
        return Project::query()->where('owner_id', $connection->owner_id)->whereNull('opus_source_id')
            ->where(fn($query) => $query->whereNull('clipping_connection_id')->orWhere('clipping_connection_id', $connection->id))
            ->where('content_mode', 'short');
    }

    /** @return Collection<int, OpusSource> */
    public function mappedSources(ClippingConnection $connection): Collection
    {
        return OpusSource::query()->where('clipping_connection_id', $connection->id)
            ->whereRelation('project', 'owner_id', $connection->owner_id)
            ->select(['id', 'project_id', 'external_id'])->addSelect(['clips_count' => OpusClip::query()->selectRaw('count(*)')->whereColumn('opus_source_id', 'opus_sources.id')])->get();
    }

    public function reviewProject(ClippingConnection $connection, int $id): ?Project
    {
        return $this->availableProjects($connection)->with('media', 'coverFrame')->find($id);
    }

    /** @return list<string> */
    public function existingClipIds(ClippingConnection $connection, ClippingImport $preview): array
    {
        return array_values(OpusClip::query()->whereHas('source', fn(Builder $source) => $source
            ->where('clipping_connection_id', $connection->id)->where('external_id', $preview->external_id)
            ->whereRelation('project', 'owner_id', $connection->owner_id))
            ->whereIn('clip_id', array_column($preview->clips, 'id'))->get(['clip_id'])->map(static fn(OpusClip $clip): string => $clip->clip_id)->all());
    }

    public function getPreview(ClippingConnection $connection, int $id): ClippingImport
    {
        return ClippingImport::query()->where('clipping_connection_id', $connection->id)->findOrFail($id);
    }

    /** @return Collection<int, OpusSource> */
    public function recentSources(User $owner): Collection
    {
        return OpusSource::query()->whereRelation('project', 'owner_id', $owner->id)
            ->whereIn('clipping_connection_id', ClippingConnection::query()->where('owner_id', $owner->id)->select('id'))
            ->orderByDesc('updated_at')->limit(100)->select(['id', 'project_id', 'clipping_connection_id', 'external_id', 'title', 'last_synced_at', 'sync_paused_at', 'sync_error'])
            ->selectRaw('publication_sync_checkpoint IS NOT NULL as publications_pending')->withCasts(['publications_pending' => 'boolean'])->get();
    }

    /**
     * @param list<int> $ids
     * @return Collection<int, Project>
     */
    public function historyProjects(User $owner, array $ids): Collection
    {
        return Project::query()->where('owner_id', $owner->id)->whereKey($ids)->with('media', 'coverFrame')->get();
    }

    /** @return Collection<int, ClippingConnection> */
    public function historyConnections(User $owner): Collection
    {
        return ClippingConnection::query()->where('owner_id', $owner->id)->orderBy('name')->get(['id', 'name', 'access_checked_at', 'access_result']);
    }

    /** @return Collection<int, ClippingImport> */
    public function history(User $owner): Collection
    {
        return ClippingImport::query()->whereIn('clipping_connection_id', ClippingConnection::query()->where('owner_id', $owner->id)->select('id'))
            ->latest('id')->limit(100)->get(['id', 'clipping_connection_id', 'external_id', 'source_name', 'project_id', 'clip_count', 'accounts', 'imported_at', 'created_at'])->each(static function (ClippingImport $preview): void {
                $preview->setAttribute('account_count', count($preview->accounts ?? []));
                $preview->makeHidden('accounts');
            });
    }

    /**
     * @param SupportCollection<int, ImportedClipData>|null $clips
     * @param list<array{id: string, platform: string, external_id: string, name: string, channel_id: int|null}> $accounts
     * @param list<array{id: string, name: string, platform: string}> $unmapped
     */
    public function preview(ClippingConnection $connection, ?OpusSourceData $source, ClippingImportData $data, ?SupportCollection $clips, array $accounts, array $unmapped): ClippingImport
    {
        return DB::transaction(function () use ($connection, $source, $data, $clips, $accounts, $unmapped): ClippingImport {
            $version = $connection->scope_version;
            $connection = ClippingConnection::query()->where('owner_id', $connection->owner_id)->lockForUpdate()->findOrFail($connection->id);

            if ($version !== $connection->scope_version) {
                throw ValidationException::withMessages(['external_id' => 'Область импорта изменилась. Повторите предпросмотр.']);
            }

            $bound = $source instanceof OpusSourceData ? OpusSource::query()->where('clipping_connection_id', $connection->id)->where('external_id', $source->id)->first()?->project_id : null;

            if ($bound !== null && $data->project_id !== null && $bound !== $data->project_id) {
                throw ValidationException::withMessages(['project_id' => 'Источник уже привязан к другому проекту.']);
            }

            $projectId = $data->project_id ?? $bound;

            if ($projectId !== null) {
                $this->availableProjects($connection)->findOrFail($projectId, ['id']);
            }

            return ClippingImport::query()->create([
                'clipping_connection_id' => $connection->id, 'project_id' => $projectId, 'external_id' => $source?->id,
                'scope_version' => $connection->scope_version, 'source_name' => $source?->title, 'game' => $data->game, 'clip_count' => $clips?->count() ?? 0,
                'accounts' => $accounts, 'unmapped_accounts' => $unmapped, 'clips' => $clips?->map(static fn(ImportedClipData $clip): array => get_object_vars($clip))->values()->all() ?? [],
            ]);
        });
    }
}
