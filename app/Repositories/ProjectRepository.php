<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\GetProjectsListData;
use App\Enums\ProductionStatus;
use App\Enums\SortDirection;
use App\Models\Project;
use App\Models\Prompt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

/** @extends EloquentRepository<Project> */
readonly class ProjectRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Project::class;
    }

    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(GetProjectsListData $data): LengthAwarePaginator
    {
        $query = Project::query()->withCount([
            'prompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'readyPrompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'channels', 'series',
        ]);

        if ($data->search !== null && $data->search !== '') {
            $search = '%' . $data->search . '%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', $search)->orWhereLike('prompt', $search);
            });
        }

        $direction = $data->sort ?? SortDirection::DESC;

        return $query
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($data->perPage ?? 10, page: $data->page ?? 1)
            ->withQueryString();
    }

    public function forWorkspace(Project $project): Project
    {
        $project->load('latestProductionRun', 'seriesDefinition');
        $project->loadCount([
            'prompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'readyPrompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'channels', 'series',
        ]);
        $project->setRelation('channels', $project
            ->channels()
            ->select('channels.id', 'name', 'type', 'is_active', 'credentials')
            ->withCount(['posts' => static fn(Builder $query) => $query
                ->where(fn(Builder $query) => $query->whereHas('prompt', fn(Builder $query) => $query->where('project_id', $project->id))
                    ->orWhereHas('episodeAssembly.episodeRevision.episode.series', fn(Builder $query) => $query->where('project_id', $project->id)))])
            ->orderBy('name')
            ->orderBy('id')
            ->get());

        return $project;
    }

    public function forSettings(Project $project): Project
    {
        return $project->load('seriesDefinition');
    }

    public function hasSeries(Project $project): bool
    {
        return $project->series()->exists();
    }

    public function hasDependencies(Project $project): bool
    {
        return $project->prompts()->exists() || $project->channels()->exists() || $project->series()->exists();
    }

    public function withBlockingProductionRuns(Project $project): Project
    {
        return $project->load([
            'productionRuns' => fn($query) => $query->whereIn('status', ProductionStatus::blockingValues())->orderBy('id'),
        ]);
    }

    /** @return LazyCollection<int, Project> */
    public function activeForProduction(?string $projectId): LazyCollection
    {
        return Project::query()->where('is_active', true)->when($projectId !== null, fn($query) => $query->whereKey($projectId))->lazyById();
    }

    public function forPromptPublication(Prompt $prompt): ?Project
    {
        $prompt->loadMissing('media');

        return $prompt->project()->with('channels')->first();
    }
}
