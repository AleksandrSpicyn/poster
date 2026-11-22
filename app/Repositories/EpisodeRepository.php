<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\EpisodeScriptClaimData;
use App\Data\Episodes\EpisodeWorkspaceData;
use App\Enums\AssemblyStatus;
use App\Enums\ChannelType;
use App\Enums\EpisodeValidity;
use App\Enums\RevisionStatus;
use App\Enums\ScriptGenerationStatus;
use App\Models\Channel;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use App\Models\Series;
use App\Models\Shot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** @extends EloquentRepository<Episode> */
readonly class EpisodeRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Episode::class;
    }

    public function workspace(Episode $episode): EpisodeWorkspaceData
    {
        $episode->load([
            'series.project',
            'currentRevision.characterRevisions.character',
            'currentRevision.scenes.shots.prompts' => static function (HasMany $query): void {
                $query->with('media', 'generationOperations')->orderByDesc('take_number')->limit(10);
            },
            'acceptedAssembly.media',
            'predecessor.acceptedAssembly.media',
        ]);
        $series = $episode->series()->firstOrFail();
        $assemblies = EpisodeAssembly::query()
            ->whereHas('episodeRevision', fn($query) => $query->where('episode_id', $episode->id))
            ->with('media')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $currentAssembly = $assemblies->first(fn(EpisodeAssembly $assembly): bool => $assembly->episode_revision_id === $episode->current_revision_id
            && $assembly->status !== AssemblyStatus::FAILED && $assembly->selection() !== []);

        if ($currentAssembly !== null && $episode->currentRevision !== null) {
            $this->includeMontageTakes($episode->currentRevision, $currentAssembly, $series->project_id);
        }

        $characters = CharacterRevision::query()
            ->where('status', RevisionStatus::APPROVED)
            ->whereHas('character', fn($query) => $query->where('series_id', $series->id)->whereNull('archived_at'))
            ->with('character')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $project = $series->project()->firstOrFail();
        $channels = $project->channels()
            ->where('channels.owner_id', $project->owner_id)->where('is_active', true)->whereNotNull('credentials->access_token')
            ->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM, ChannelType::TIKTOK])
            ->orderBy('name')->limit(100)->get()
            ->reject(fn(Channel $channel): bool => $channel->type === ChannelType::TIKTOK && ! $channel->canSendTiktokInboxVideo())->values();
        $publications = PublicationRequest::query()->whereIn('episode_assembly_id', $assemblies->modelKeys())
            ->where('created_by', $project->owner_id)->with('channel', 'post')->orderByDesc('id')->limit(200)->get();

        return new EpisodeWorkspaceData($episode, $characters, $assemblies, $publications, $channels);
    }

    private function includeMontageTakes(EpisodeRevision $revision, EpisodeAssembly $assembly, int $projectId): void
    {
        $shots = $revision->scenes->flatMap(fn($scene) => $scene->shots)->keyBy('id');
        $selected = collect($assembly->selection())->pluck('prompt_id', 'shot_id');
        $missing = $shots->filter(fn(Shot $shot): bool => $selected->has($shot->id) && ! $shot->prompts->contains('id', $selected->get($shot->id)));

        if ($missing->isEmpty()) {
            return;
        }

        $prompts = Prompt::query()->where('project_id', $projectId)->whereIn('shot_id', $missing->keys())
            ->whereIn('id', $selected->only($missing->keys()->all()))->with('media', 'generationOperations')->get();

        foreach ($prompts as $prompt) {
            $shot = $missing->get($prompt->shot_id);

            if ($shot !== null && $selected->get($shot->id) === $prompt->id) {
                $shot->prompts->push($prompt);
            }
        }
    }

    /** @return Collection<int, Episode> */
    public function expireScriptClaims(int $limit): Collection
    {
        $expiredIds = Episode::query()->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->where('script_started_at', '<', now()->subMinutes(10))
            ->orderBy('id')->limit($limit)->pluck('id');

        Episode::query()->whereKey($expiredIds)
            ->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->where('script_started_at', '<', now()->subMinutes(10))
            ->update(['script_generation_status' => ScriptGenerationStatus::OUTCOME_UNKNOWN, 'script_generation_error' => 'The worker stopped before confirming a result. Review before requesting another draft.']);

        return Episode::query()->whereKey($expiredIds)->get();
    }

    /** @return Collection<int, Episode> */
    public function pendingScripts(int $limit): Collection
    {
        return Episode::query()->where('script_generation_status', ScriptGenerationStatus::QUEUED)
            ->orderBy('id')->limit($limit)->get();
    }

    public function deferScriptRequest(Episode $episode, string $requestKey): int
    {
        return Episode::query()->whereKey($episode->id)->where('script_request_key', $requestKey)
            ->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->update(['script_generation_status' => ScriptGenerationStatus::QUEUED,
                'script_started_at' => null, 'script_generation_error' => null]);
    }

    public function completeScriptRequest(Episode $episode, string $requestKey, ?string $error): int
    {
        return Episode::query()->whereKey($episode->id)->where('script_request_key', $requestKey)
            ->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->update([
                'script_generation_status' => $error === null ? ScriptGenerationStatus::COMPLETED : ScriptGenerationStatus::FAILED,
                'script_generation_error' => $error,
            ]);
    }

    public function forRevision(EpisodeRevision $revision): Episode
    {
        return $revision->episode()->firstOrFail();
    }

    public function forSeries(int $seriesId, int $id): Episode
    {
        return Episode::query()->where('series_id', $seriesId)->findOrFail($id);
    }

    public function invalidateLater(Episode $episode, int $seriesId): int
    {
        return Episode::query()->where('series_id', $seriesId)->where('number', '>', $episode->number)
            ->update(['validity' => EpisodeValidity::REQUIRES_REVIEW->value, 'lock_version' => DB::raw('lock_version + 1')]);
    }

    /** @return Collection<int, Episode> */
    public function acceptedHistory(Episode $episode, int $seriesId): Collection
    {
        return Episode::query()->where('series_id', $seriesId)
            ->where('number', '<', $episode->number)
            ->where('validity', EpisodeValidity::CURRENT)
            ->whereHas('acceptedAssembly', fn($query) => $query->where('status', AssemblyStatus::ACCEPTED))
            ->with('acceptedAssembly.episodeRevision')
            ->orderByDesc('number')->limit(50)->get()->reverse()->values();
    }

    public function otherCycleShotCount(Episode $episode): int
    {
        return Shot::query()->whereHas('scene.episodeRevision', fn($query) => $query->whereHas('episode', fn($episodes) => $episodes->where('series_id', $episode->series_id)->whereKeyNot($episode->id)->whereColumn('episodes.current_revision_id', 'episode_revisions.id')))->count();
    }

    public function latestForSeries(Series $series): ?Episode
    {
        return $series->episodes()->orderByDesc('number')->first();
    }

    public function createNext(Series $series, ?Episode $previous, string $title): Episode
    {
        return $series->episodes()->create([
            'title' => $title,
            'number' => ($previous->number ?? 0) + 1,
            'predecessor_episode_id' => $previous?->id,
        ])->refresh();
    }

    /** Project → Series → Episode: тот же порядок, что при изменении настроек проекта. */
    public function lockScriptClaim(Episode $episode): EpisodeScriptClaimData
    {
        $series = Series::query()->findOrFail($episode->series_id);
        $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);
        $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);
        $episode = Episode::query()->lockForUpdate()->findOrFail($episode->id);

        return new EpisodeScriptClaimData($project, $series, $episode);
    }
}
