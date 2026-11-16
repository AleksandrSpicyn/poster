<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
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
            'assembly.media', 'assembly.publications', 'prompt.posts',
        ])->findOrFail($id);
    }

    /** @return Collection<int, ProductionRun> */
    public function due(int $limit): Collection
    {
        return ProductionRun::query()
            ->whereIn('status', [ProductionStatus::QUEUED, ProductionStatus::RUNNING, ProductionStatus::WAITING])
            ->where('next_check_at', '<=', now())->orderBy('id')->limit($limit)->get();
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
            'stage' => ProductionStage::PREPARING, 'next_check_at' => now(),
        ]);
    }
}
