<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\SeriesOutputMode;
use App\Events\WorkspaceUpdated;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** @extends EloquentRepository<ProductionRun> */
readonly class ProductionRunRepository extends EloquentRepository
{
    protected function model(): string
    {
        return ProductionRun::class;
    }

    public function forProduction(int $id): ProductionRun
    {
        return ProductionRun::query()->with([
            'actor', 'project.channels',
            'series.automationUser', 'series.characters.currentRevision.media',
            'series.characters.revisions' => fn($query) => $query->with('media')->orderByDesc('number'),
            'series.episodes' => fn($query) => $query->orderByDesc('number')->limit(1),
            'episode.currentRevision.scenes.shots.prompts.operations',
            'episode.currentRevision.scenes.shots.prompts.media',
            'seriesAssembly.media', 'assembly.media', 'assembly.publications', 'prompt.posts',
        ])->findOrFail($id);
    }

    /** @return Collection<int, ProductionRun> */
    public function due(int $limit): Collection
    {
        return ProductionRun::query()
            ->whereIn('status', [ProductionStatus::QUEUED, ProductionStatus::RUNNING, ProductionStatus::WAITING])
            ->where('next_check_at', '<=', now())->orderBy('id')->limit($limit)->get();
    }

    public function cycleForAssembly(SeriesAssembly $assembly): ?ProductionRun
    {
        return ProductionRun::query()->where('series_id', $assembly->series_id)->whereIn('status', ProductionStatus::blockingValues())->latest('id')->lockForUpdate()->first();
    }

    public function hasUnresolvedEffects(ProductionRun $run): bool
    {
        return GenerationOperation::query()->whereHas('prompt.shot.scene.episodeRevision.episode', fn($query) => $query->where('series_id', $run->series_id))
            ->whereIn('status', ['submission_unknown', 'submitting', 'submitted', 'running'])->exists()
            || PublicationRequest::query()->where('production_run_id', $run->id)->whereIn('status', ['submission_unknown', 'submitting', 'publishing_container'])->exists();
    }

    public function forRequestKey(string $key): ?ProductionRun
    {
        return ProductionRun::query()->where('request_key', $key)->first();
    }

    public function blockingForProject(Project $project, ?Series $series): ?ProductionRun
    {
        return $project->productionRuns()->where('series_id', $series?->id)
            ->whereIn('status', ProductionStatus::blockingValues())->first();
    }

    public function createQueued(Project $project, ?Series $series, User $actor, string $key): ProductionRun
    {
        $snapshot = $series?->output_mode === SeriesOutputMode::SINGLE_VIDEO
            ? $series->productionRuns()->whereNotNull('input_snapshot')->oldest('id')->first()?->input_snapshot : null;
        $attributes = $this->queuedAttributes($project, $series, $actor, $key, $snapshot);

        return $project->productionRuns()->chaperone('project')->create($attributes);
    }

    /**
     * @param list<string> $keys
     * @return Collection<string, ProductionRun>
     */
    public function byRequestKeys(array $keys): Collection
    {
        $runs = new Collection();

        foreach (array_chunk($keys, 1000) as $batch) {
            foreach (ProductionRun::query()->whereIn('request_key', $batch)->get() as $run) {
                $runs->put($run->request_key, $run);
            }
        }

        return $runs;
    }

    /**
     * @param array<string, Series> $seriesByKey
     * @return Collection<string, ProductionRun>
     */
    public function createQueuedSeries(Project $project, User $actor, array $seriesByKey): Collection
    {
        $rows = [];
        $channelIds = null;

        foreach ($seriesByKey as $key => $series) {
            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO && $series->productionRuns->isEmpty()) {
                $channelIds ??= $this->deliveryChannelIds($project);
            }

            $rows[] = $this->queuedAttributes($project, $series, $actor, $key, $series->productionRuns->first()?->input_snapshot, $channelIds);
        }

        foreach (array_chunk($rows, 100) as $batch) {
            ProductionRun::query()->fillAndInsert($batch);
        }

        $runs = $this->byRequestKeys(array_keys($seriesByKey));

        foreach ($runs as $run) {
            $run->wasRecentlyCreated = true;
            $run->setRelation('project', $project);
            WorkspaceUpdated::forResource($run);
        }

        return $runs;
    }

    /**
     * @param array<string, mixed>|null $snapshot
     * @param list<int>|null $channelIds
     * @return array<string, mixed>
     */
    private function queuedAttributes(Project $project, ?Series $series, User $actor, string $key, ?array $snapshot, ?array $channelIds = null): array
    {
        $single = $series?->output_mode === SeriesOutputMode::SINGLE_VIDEO;

        if ($single) {
            $snapshot ??= [
                'output_mode' => $series->output_mode->value,
                'episode_limit' => $series->episode_limit,
                'specification_version' => $series->specification_version,
                'specification' => $series->specification,
                'channel_ids' => $channelIds ?? $this->deliveryChannelIds($project),
            ];
        }

        return [
            'project_id' => $project->id, 'series_id' => $series?->id, 'actor_id' => $actor->id,
            'request_key' => $key, 'status' => ProductionStatus::QUEUED,
            'stage' => $single ? ProductionStage::PLANNING : ProductionStage::PREPARING,
            'input_snapshot' => $single ? $snapshot : null, 'stage_started_at' => now(), 'next_check_at' => now(),
        ];
    }

    /** @return list<int> */
    private function deliveryChannelIds(Project $project): array
    {
        /** @var list<int> $ids */
        $ids = $project->channels()->where('is_active', true)->where(fn($q) => $q->whereIn('type', ['youtube', 'instagram'])->orWhere(fn($tiktok) => $tiktok->where('type', 'tiktok')->whereNull('external_account')->whereNotNull('credentials->access_token')->where('credentials->scope', 'like', '%video.upload%')))->orderBy('id')->pluck('channels.id')->all();

        return $ids;
    }
}
