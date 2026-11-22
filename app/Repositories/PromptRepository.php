<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Data\GetPromptsListData;
use App\Enums\SortDirection;
use App\Models\Episode;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/** @extends EloquentRepository<Prompt> */
readonly class PromptRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Prompt::class;
    }

    /** @return LengthAwarePaginator<int, Prompt> */
    public function paginate(User $owner, GetPromptsListData $dto, ?Project $project = null): LengthAwarePaginator
    {
        $direction = $dto->sort instanceof SortDirection ? $dto->sort : SortDirection::DESC;

        $query = $project === null ? Prompt::query()->with('project') : $project->prompts()->chaperone('project');

        if ($project === null && is_int($dto->project)) {
            $query->where('project_id', $dto->project);
        }

        return $query->whereRelation('project', 'owner_id', $owner->id)->whereNull('shot_id')->with('media')
            ->orderBy('created_at', $direction->value)
            ->orderBy('id', $direction->value)
            ->paginate($dto->perPage ?? 10, page: $dto->page ?? 1)
            ->withQueryString();
    }

    public function createForProject(Project $project, string $idea): Prompt
    {
        /** @var Prompt $prompt */
        $prompt = $project->prompts()->create(['prompt' => $idea]);

        return $prompt;
    }

    /** @return Collection<int, string> */
    public function recentIdeas(Project $project): Collection
    {
        return $project->prompts()->whereNull('shot_id')->latest()->limit(50)->get(['prompt'])->map(fn(Prompt $prompt): string => $prompt->prompt);
    }

    /** @return array{total: int, ready: int} */
    public function dashboardCounts(User $owner): array
    {
        /** @var object{total: int|string, ready: int|string} $counts */
        $counts = Prompt::query()->whereRelation('project', 'owner_id', $owner->id)->whereNull('shot_id')
            ->toBase()->selectRaw('count(*) as total, count(case when prompts.generated then 1 end) as ready')->first();

        return ['total' => (int) $counts->total, 'ready' => (int) $counts->ready];
    }

    /** @return EloquentCollection<int, Prompt> */
    public function recentForDashboard(User $owner): EloquentCollection
    {
        return Prompt::query()->whereRelation('project', 'owner_id', $owner->id)->whereNull('shot_id')->with('project', 'media')->latest()->latest('id')->limit(6)->get();
    }

    public function forAudioTask(string $taskId): ?Prompt
    {
        return Prompt::query()->firstWhere('audio_id', $taskId);
    }

    /**
     * @param list<int> $sourceIds
     * @return EloquentCollection<int, Prompt>
     */
    public function readySources(Episode $episode, array $sourceIds): EloquentCollection
    {
        return Prompt::query()->whereIn('id', $sourceIds)->where('generated', true)
            ->whereHas('shot.scene.episodeRevision', fn($query) => $query->where('episode_id', $episode->id))
            ->with('shot.scene')->get()->keyBy('id');
    }

    /** @return EloquentCollection<int, Prompt> */
    public function assemblySelection(AssemblyManifestData $data): EloquentCollection
    {
        return Prompt::query()->with('media')->whereIn('id', array_map(fn(AssemblyShotData $shot): int => $shot->prompt_id, $data->shots))->get()->keyBy('id');
    }

    public function cycleTakeCount(int $seriesId): int
    {
        return Prompt::query()->whereHas('shot.scene.episodeRevision.episode', fn($query) => $query->where('series_id', $seriesId))->count();
    }

    /** @return EloquentCollection<int, Prompt> */
    public function lockEpisodeTakes(ProductionRun $run): EloquentCollection
    {
        return Prompt::query()->where(fn($query) => $query
            ->whereHas('generationOperations', fn($operations) => $operations->where('production_run_id', $run->id))
            ->orWhereHas('shot.scene.episodeRevision', fn($revisions) => $revisions->where('episode_id', $run->episode_id)))
            ->with(['project', 'generationOperations' => fn($query) => $query->chaperone('prompt')->orderBy('id')->lockForUpdate()])
            ->orderBy('id')->lockForUpdate()->get();
    }

    public function forOperation(GenerationOperation $operation): Prompt
    {
        return $operation->prompt()->with('media')->firstOrFail();
    }

    public function withGenerationOperations(Prompt $prompt): Prompt
    {
        return $prompt->load('generationOperations');
    }

    public function latestForShot(Shot $shot): ?Prompt
    {
        return $shot->prompts()->latest('take_number')->first();
    }

    public function forContinuation(int $sourcePromptId): Prompt
    {
        return Prompt::query()->with('shot.scene.episodeRevision', 'media')->findOrFail($sourcePromptId);
    }

    public function isShotTake(int|string $id): bool
    {
        return Prompt::query()->whereNotNull('shot_id')->whereKey($id)->exists();
    }

    /** @return LazyCollection<int, Prompt> */
    public function unfinished(): LazyCollection
    {
        return Prompt::query()->where('generated', false)->whereNull('shot_id')->whereNotNull('video_url')->whereNotNull('audio_url')->lazyById();
    }

    public function refreshForAssembly(Prompt $prompt): Prompt
    {
        return $prompt->refresh()->load('project', 'media');
    }

    public function loadMedia(Prompt $prompt): Prompt
    {
        return $prompt->loadMissing('media');
    }

    /** Обновляет кэш связи после записи файла перед передачей Prompt в событие. */
    public function reloadMedia(Prompt $prompt): Prompt
    {
        return $prompt->load('media');
    }
}
