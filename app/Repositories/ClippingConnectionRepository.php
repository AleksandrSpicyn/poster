<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\ClippingConnectionData;
use App\Data\ClippingImportData;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Models\OpusSource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

final readonly class ClippingConnectionRepository
{
    /** @return Collection<int, ClippingConnection> */
    public function owned(User $owner): Collection
    {
        return ClippingConnection::query()->where('owner_id', $owner->id)->orderBy('name')->get(['id', 'name', 'driver']);
    }

    /** @return LazyCollection<int, OpusSource> */
    public function scheduledSources(): LazyCollection
    {
        return OpusSource::query()->whereNull('sync_paused_at')
            ->whereIn('clipping_connection_id', ClippingConnection::query()->where('uses_server_key', false)->select('id'))->lazyById();
    }

    public function activeConnection(int $connectionId, int $sourceId): ?ClippingConnection
    {
        $connection = ClippingConnection::query()->find($connectionId);

        if ($connection === null || ! OpusSource::query()->whereKey($sourceId)->where('clipping_connection_id', $connectionId)
            ->whereNull('sync_paused_at')->whereHas('project', fn($query) => $query->where('owner_id', $connection->owner_id)
            ->where('clipping_connection_id', $connectionId))->exists()) {
            return null;
        }

        return $connection;
    }

    public function markSyncError(int $connectionId, int $sourceId, string $message, bool $pause = false): void
    {
        OpusSource::query()->whereKey($sourceId)->where('clipping_connection_id', $connectionId)
            ->update(['sync_error' => $message, ...($pause ? ['sync_paused_at' => now()] : [])]);
    }

    public function save(User $owner, ClippingConnectionData $data): ClippingConnection
    {
        $connection = new ClippingConnection(['name' => $data->name, 'driver' => $data->driver, 'api_key' => $data->api_key]);
        $connection->owner_id = $owner->id;
        $connection->save();

        return $connection;
    }

    /** @return Collection<int, Project> */
    public function projects(ClippingConnection $connection): Collection
    {
        return Project::query()->where('owner_id', $connection->owner_id)->whereNull('opus_source_id')
            ->where('content_mode', 'short')->orderBy('name')->get(['id', 'name']);
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
            ->orderByDesc('updated_at')->limit(100)->get(['id', 'external_id', 'title', 'last_synced_at', 'sync_paused_at', 'sync_error']);
    }

    /** @return Collection<int, ClippingImport> */
    public function history(User $owner): Collection
    {
        return ClippingImport::query()->whereIn('clipping_connection_id', ClippingConnection::query()->where('owner_id', $owner->id)->select('id'))
            ->latest('id')->limit(100)->get(['id', 'clipping_connection_id', 'external_id', 'source_name', 'project_id', 'clip_count', 'imported_at', 'created_at']);
    }

    /**
     * @param SupportCollection<int, ImportedClipData> $clips
     * @param list<array{id: string, platform: string, external_id: string, name: string, channel_id: int|null}> $accounts
     */
    public function preview(ClippingConnection $connection, OpusSourceData $source, ClippingImportData $data, SupportCollection $clips, array $accounts): ClippingImport
    {
        return DB::transaction(function () use ($connection, $source, $data, $clips, $accounts): ClippingImport {
            $connection = ClippingConnection::query()->where('owner_id', $connection->owner_id)->lockForUpdate()->findOrFail($connection->id);
            $bound = OpusSource::query()->where('clipping_connection_id', $connection->id)->where('external_id', $source->id)->first()?->project_id;

            if ($bound !== null && $data->project_id !== null && $bound !== $data->project_id) {
                throw ValidationException::withMessages(['project_id' => 'Источник уже привязан к другому проекту.']);
            }

            $projectId = $data->project_id ?? $bound;

            if ($projectId !== null) {
                Project::query()->where('owner_id', $connection->owner_id)->whereNull('opus_source_id')->where('content_mode', 'short')->findOrFail($projectId);
            }

            return ClippingImport::query()->create([
                'clipping_connection_id' => $connection->id, 'project_id' => $projectId, 'external_id' => $source->id,
                'source_name' => $source->title, 'game' => $data->game, 'clip_count' => $clips->count(),
                'accounts' => $accounts, 'clips' => $clips->map(static fn(ImportedClipData $clip): array => get_object_vars($clip))->values()->all(),
            ]);
        });
    }
}
