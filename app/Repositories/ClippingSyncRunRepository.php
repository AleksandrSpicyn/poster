<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ChannelMetricSync;
use App\Models\ClippingConnection;
use App\Models\ClippingSyncRun;
use App\Models\OpusSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ClippingSyncRunRepository
{
    /** The caller holds the source row lock until its checkpoint and the run are committed together. */
    public function open(OpusSource $source): ClippingSyncRun
    {
        return ClippingSyncRun::query()->firstOrCreate(['opus_source_id' => $source->id, 'completed_at' => null]);
    }

    /** @return Collection<int, ClippingSyncRun> */
    public function history(User $owner): Collection
    {
        return ClippingSyncRun::query()->whereHas('source', fn($query) => $query
            ->whereRelation('project', 'owner_id', $owner->id)
            ->whereIn('clipping_connection_id', ClippingConnection::query()->where('owner_id', $owner->id)->select('id')))
            ->select('clipping_sync_runs.*')
            ->selectSub(DB::table('clipping_sync_observations')->selectRaw('count(*)')->whereColumn('clipping_sync_run_id', 'clipping_sync_runs.id'), 'metrics_added')
            ->latest('id')->limit(100)->get();
    }

    /** @return Collection<int, ChannelMetricSync> */
    public function metricStatuses(User $owner): Collection
    {
        return ChannelMetricSync::query()->join('channels', 'channels.id', '=', 'channel_metric_syncs.channel_id')
            ->join('clipping_account_mappings as mapping', 'mapping.channel_id', '=', 'channels.id')
            ->join('clipping_connections', 'clipping_connections.id', '=', 'mapping.clipping_connection_id')
            ->where('channel_metric_syncs.owner_id', $owner->id)->where('channels.owner_id', $owner->id)->where('clipping_connections.owner_id', $owner->id)
            ->select('clipping_connections.id as connection_id', 'channels.name', 'channel_metric_syncs.status', 'channel_metric_syncs.updated_at')
            ->distinct()->latest('channel_metric_syncs.updated_at')->limit(100)->get();
    }

    /**
     * Index only new persisted daily observations. Both metric readers can encounter the same publication.
     *
     * @param array<string, list<int>> $origins keyed by the canonical publication and UTC observation day
     */
    public function observations(array $origins): void
    {
        if ($origins === []) {
            return;
        }

        $runs = DB::table('clipping_sync_runs')->join('opus_clips', 'opus_clips.opus_source_id', '=', 'clipping_sync_runs.opus_source_id')
            ->whereIn('opus_clips.id', collect($origins)->flatten()->unique())->groupBy('opus_clips.id', 'opus_clips.opus_source_id')
            ->select('opus_clips.id', 'opus_clips.opus_source_id')->selectRaw('max(clipping_sync_runs.id) as run_id')->get()->keyBy('id');
        $rows = [];

        foreach ($origins as $key => $ids) {
            foreach ($runs->only($ids)->unique('opus_source_id') as $run) {
                $rows[] = ['clipping_sync_run_id' => $run->run_id, 'opus_source_id' => $run->opus_source_id, 'observation_key' => $key];
            }
        }

        if ($rows !== []) {
            DB::table('clipping_sync_observations')->insertOrIgnore($rows);
        }
    }

    public static function observationKey(string $platform, string $account, string $mediaId, string $day): string
    {
        return hash('sha256', serialize([$platform, $account, $mediaId, $day]));
    }
}
