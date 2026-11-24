<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\GetProjectsListData;
use App\Enums\ProductionStatus;
use App\Enums\SortDirection;
use App\Events\WorkspaceUpdated;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/** @extends EloquentRepository<Project> */
readonly class ProjectRepository extends EloquentRepository
{
    public function lockForProduction(int $id): Project
    {
        return Project::query()->addSelect(['generation_accounting_mode' => DB::table('billing_accounts')->select('accounting_mode')->whereColumn('owner_id', 'projects.owner_id')])
            ->lockForUpdate()->findOrFail($id);
    }

    protected function model(): string
    {
        return Project::class;
    }

    /** @return LengthAwarePaginator<int, Project> */
    public function paginate(User $owner, GetProjectsListData $data): LengthAwarePaginator
    {
        $query = Project::query()->where('owner_id', $owner->id)->with('media', 'coverFrame')->withCount([
            'prompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'readyPrompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'channels' => static fn(Builder $query) => $query->where('channels.owner_id', $owner->id),
            'series',
            'productionRuns as blocking_runs_count' => static fn(Builder $query) => $query->whereIn('status', ProductionStatus::blockingValues()),
        ]);

        if ($data->search !== null && $data->search !== '') {
            $search = '%' . $data->search . '%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', $search)->orWhereLike('prompt', $search);
            });
        }

        $direction = $data->sort ?? SortDirection::DESC;

        return $query
            ->whereNull('archived_at', not: $data->archived)
            ->when($data->clipping, fn(Builder $query) => $query->where(fn(Builder $projects) => $projects
                ->whereNotNull('clipping_connection_id')->orWhereNotNull('clipping_workspace')->orWhereNotNull('opus_source_id')))
            ->when($data->active, fn($query) => $query->where('is_active', true))
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($data->perPage ?? 10, page: $data->page ?? 1)
            ->withQueryString();
    }

    public function setArchived(Project $project, bool $archived): void
    {
        $changed = Project::query()->whereKey($project->id)->whereNull('archived_at', not: ! $archived)
            ->update(['archived_at' => $archived ? now() : null]);

        if ($changed > 0) {
            WorkspaceUpdated::forResource($project);
        }
    }

    public function information(User $owner, int $id): Project
    {
        return Project::query()->where('owner_id', $owner->id)->with([
            'media',
            'coverFrame',
            'channels' => fn(BelongsToMany $channels) => $channels->where('channels.owner_id', $owner->id),
            'series' => fn(HasMany $series) => $series->withCount(['episodes', 'episodes as accepted_episodes_count' => fn(Builder $episodes) => $episodes->whereNotNull('accepted_assembly_id')]),
        ])->withCount('prompts', 'readyPrompts')->findOrFail($id);
    }

    /** @return list<array{id: int, name: string}> */
    public function analyticsOptions(User $owner): array
    {
        return array_values(Project::query()->where('owner_id', $owner->id)->whereNull('opus_source_id')
            ->orderBy('name')->get(['id', 'name'])->map(static fn(Project $project): array => ['id' => $project->id, 'name' => $project->name])->all());
    }

    public function forWorkspace(Project $project): Project
    {
        $project->load('latestProductionRun', 'seriesDefinition', 'media', 'coverFrame');
        $project->seriesDefinition?->setRelation('project', $project->withoutRelations()
            ->setRelation('media', $project->getRelation('media'))
            ->setRelation('coverFrame', $project->getRelation('coverFrame')));
        $project->loadCount([
            'prompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'readyPrompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'channels' => static fn(Builder $query) => $query->where('channels.owner_id', $project->owner_id),
            'series',
            'productionRuns as blocking_runs_count' => static fn(Builder $query) => $query->whereIn('status', ProductionStatus::blockingValues()),
        ]);
        $project->setRelation('channels', $project
            ->channels()
            ->where('channels.owner_id', $project->owner_id)
            ->select('channels.id', 'name', 'type', 'is_active', 'credentials')
            ->withCount(['posts' => static fn(Builder $query) => $query
                ->where(fn(Builder $query) => $query->whereHas('prompt', fn(Builder $query) => $query->where('project_id', $project->id))
                    ->orWhereHas('episodeAssembly.episodeRevision.episode.series', fn(Builder $query) => $query->where('project_id', $project->id))
                    ->orWhereHas('seriesAssembly.series', fn(Builder $query) => $query->where('project_id', $project->id)))])
            ->orderBy('name')
            ->orderBy('id')
            ->get());

        return $project;
    }

    public function forSettings(Project $project): Project
    {
        return $project->load([
            'seriesDefinition' => fn($query) => $query->withMax('episodes', 'number'),
            'seriesDefinition.characters' => fn($query) => $query->whereNull('archived_at')
                ->whereNotNull('current_revision_id')->with('currentRevision.media')->orderBy('name'),
            'media',
            'coverFrame',
        ])->loadCount([
            'prompts',
            'channels',
            'series',
            'productionRuns as blocking_runs_count' => static fn(Builder $query) => $query->whereIn('status', ProductionStatus::blockingValues()),
        ]);
    }

    public function hasSeries(Project $project): bool
    {
        return $project->series()->exists();
    }

    public function hasDependencies(Project $project): bool
    {
        return $project->isClippingProject() || $project->prompts()->exists() || $project->channels()->exists() || $project->series()->exists()
            || $project->productionRuns()->whereIn('status', ProductionStatus::blockingValues())->exists();
    }

    public function withBlockingProductionRuns(Project $project): Project
    {
        return $project->load([
            'productionRuns' => fn($query) => $query->whereIn('status', ProductionStatus::blockingValues())->orderBy('id'),
        ]);
    }

    /** @return LazyCollection<int, Project> */
    public function activeForProduction(?string $projectId, ?bool $projectClock = null): LazyCollection
    {
        return Project::query()->with('owner', 'automatedSeries.automationUser')->where('is_active', true)
            ->when($projectClock === true, fn($query) => $query->where('schedule_enabled', true))
            ->when($projectClock === false, fn($query) => $query->whereNull('schedule_enabled'))
            ->when($projectId !== null, fn($query) => $query->whereKey($projectId))->lazyById();
    }

    public function forPromptPublication(Prompt $prompt): ?Project
    {
        $prompt->loadMissing('media');

        return $prompt->project()->with('channels')->first();
    }
}
