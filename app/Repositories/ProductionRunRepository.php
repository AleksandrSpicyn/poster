<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\SeriesOutputMode;
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

    public function existsForSeries(Series $series): bool
    {
        return $series->productionRuns()->exists();
    }

    public function createQueued(Project $project, ?Series $series, User $actor, string $key): ProductionRun
    {
        return $project->productionRuns()->create([
            'series_id' => $series?->id, 'actor_id' => $actor->id,
            'request_key' => $key, 'status' => ProductionStatus::QUEUED,
            'stage' => $series?->output_mode === SeriesOutputMode::SINGLE_VIDEO ? ProductionStage::PLANNING : ProductionStage::PREPARING,
            'input_snapshot' => $series?->output_mode === SeriesOutputMode::SINGLE_VIDEO ? ($series->productionRuns()->whereNotNull('input_snapshot')->oldest('id')->first()->input_snapshot ?? [
                'output_mode' => $series->output_mode->value,
                'episode_limit' => $series->episode_limit,
                'specification_version' => $series->specification_version,
                'specification' => $series->specification,
                'channel_ids' => $project->channels()->where('is_active', true)->whereIn('type', ['youtube', 'instagram'])->orderBy('id')->pluck('channels.id')->all(),
            ]) : null,
            'stage_started_at' => now(), 'next_check_at' => now(),
        ]);
    }
}
