<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\SeriesOutputMode;
use App\Events\WorkspaceUpdated;
use App\Models\Channel;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\SeriesAssembly;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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
            'actor',
            'project.channels',
            'series.automationUser',
            'series.characters.currentRevision.media',
            'series.characters.revisions' => fn($query) => $query->with('media')->orderByDesc('number'),
            'series.episodes' => fn($query) => $query->orderByDesc('number')->limit(1),
            'episode.currentRevision.scenes.shots.prompts.operations',
            'episode.currentRevision.scenes.shots.prompts.media',
            'seriesAssembly.media',
            'assembly.media',
            'assembly.publications',
            'prompt.posts',
        ])->findOrFail($id);
    }

    /** @return Collection<int, ProductionRun> */
    public function due(int $limit): Collection
    {
        return ProductionRun::query()
            ->whereIn('status', [
                ProductionStatus::QUEUED,
                ProductionStatus::RUNNING,
                ProductionStatus::WAITING,
            ])
            ->where('next_check_at', '<=', now())->orderBy('id')->limit($limit)->get();
    }

    public function cycleForAssembly(SeriesAssembly $assembly): ?ProductionRun
    {
        return ProductionRun::query()->where('series_id', $assembly->series_id)->whereIn('status', ProductionStatus::blockingValues())->latest('id')->lockForUpdate()->first();
    }

    public function hasUnresolvedEffects(ProductionRun $run): bool
    {
        return GenerationOperation::query()->whereHas('prompt.shot.scene.episodeRevision.episode', fn($query) => $query->where('series_id', $run->series_id))
            ->whereIn('status', [
                'submission_unknown',
                'submitting',
                'submitted',
                'running',
            ])->exists()
            || PublicationRequest::query()->where('production_run_id', $run->id)->whereIn('status', [
                'submission_unknown',
                'submitting',
                'publishing_container',
            ])->exists();
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

    /** @param array<string, mixed>|null $snapshot */
    public function createQueued(Project $project, ?Series $series, User $actor, string $key, ?array $snapshot = null): ProductionRun
    {
        $snapshot ??= $series?->output_mode === SeriesOutputMode::SINGLE_VIDEO
            ? $series->productionRuns()->where('input_snapshot->output_mode', SeriesOutputMode::SINGLE_VIDEO->value)->oldest('id')->first()?->input_snapshot : null;
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
     * @param list<int>|null $selectedIds
     * @param array<string, Series> $seriesByKey
     * @return Collection<string, ProductionRun>
     */
    public function createQueuedSeries(Project $project, User $actor, array $seriesByKey, ?array $selectedIds = null): Collection
    {
        $rows = [];
        $channelIds = null;

        foreach ($seriesByKey as $key => $series) {
            $single = $series->output_mode === SeriesOutputMode::SINGLE_VIDEO;
            $episode = $series->episodes->first();
            $cycle = $single ? $series->productionRuns->first() : null;
            $pinned = $cycle?->input_snapshot;
            $ids = $single ? $cycle?->deliveryChannelIds() : ($episode?->accepted_assembly_id === null ? $episode?->delivery_channel_ids : null);

            if ($ids !== null && $selectedIds !== null && (array_diff($ids, $selectedIds) !== [] || array_diff($selectedIds, $ids) !== [])) {
                throw ValidationException::withMessages(['channel_ids' => 'Получатели этого выпуска уже сохранены. Обновите страницу.']);
            }

            if ($ids === null && $selectedIds === null && $series->auto_publish) {
                $channelIds ??= $this->deliveryChannelIds($project);
            }

            $ids ??= $selectedIds ?? ($series->auto_publish ? $channelIds : []);
            $snapshot = $pinned ?? [
                'channel_ids' => ! $single && $selectedIds === null && $series->auto_publish && $ids === [] && $episode?->delivery_channel_ids === null ? null : $ids,
                'manual_delivery' => $selectedIds !== null,
                'skip_delivery' => $ids === [] && ($selectedIds !== null || ! $series->auto_publish || $episode?->delivery_channel_ids === []),
            ];
            $rows[] = $this->queuedAttributes($project, $series, $actor, $key, $snapshot, $ids);
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
            $snapshot = [
                ...($snapshot ?? []),
                'output_mode' => $series->output_mode->value,
                'episode_limit' => $snapshot['episode_limit'] ?? $series->episode_limit,
                'specification_version' => $snapshot['specification_version'] ?? $series->specification_version,
                'specification' => $snapshot['specification'] ?? $series->specification,
                'channel_ids' => $snapshot['channel_ids'] ?? $channelIds ?? $this->deliveryChannelIds($project),
            ];
        }

        return [
            'project_id' => $project->id,
            'series_id' => $series?->id,
            'actor_id' => $actor->id,
            'request_key' => $key,
            'status' => ProductionStatus::QUEUED,
            'stage' => $single ? ProductionStage::PLANNING : ProductionStage::PREPARING,
            'input_snapshot' => $snapshot,
            'stage_started_at' => now(),
            'next_check_at' => now(),
        ];
    }

    /** @return list<int> */
    public function deliveryChannelIds(Project $project): array
    {
        return array_values($project->channels()->where('channels.owner_id', $project->owner_id)->where('is_active', true)->orderBy('channels.id')
            ->get()->filter(fn(Channel $channel): bool => $channel->canReceiveProduction())
            ->map(fn(Channel $channel): int => $channel->id)->all());
    }
}
