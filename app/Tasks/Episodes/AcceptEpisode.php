<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\AcceptEpisodeData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\RevisionStatus;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\ProductionRun;
use App\Models\User;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\EpisodeRevisionRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AcceptEpisode
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly EpisodeAssemblyRepository $assemblies,
        private readonly EpisodeRevisionRepository $episodeRevisions,
        private readonly SeriesRepository $series,
        private readonly UserRepository $users,
        private readonly ProjectRepository $projects,
        private readonly ProductionRunRepository $runs,
    ) {}

    public function run(EpisodeAssembly $assembly, AcceptEpisodeData $data, User $actor, ?ProductionRun $run = null): EpisodeAssembly
    {
        $actor = $this->users->fresh($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        return DB::transaction(function () use ($assembly, $data, $actor, $run): EpisodeAssembly {
            $revision = $this->episodeRevisions->findOrFail($assembly->episode_revision_id);
            $episode = $this->episodes->findOrFail($revision->episode_id);
            $definition = $this->series->findOrFail($episode->series_id);
            $project = $this->projects->findLockedOrFail($definition->project_id);
            $series = $this->series->findLockedOrFail($episode->series_id);

            if ($run instanceof ProductionRun) {
                $current = $this->runs->findLockedOrFail($run->id);

                if (! $current->status->isActive() || $current->episode_id !== $episode->id || $current->episode_assembly_id !== $assembly->id) {
                    throw ValidationException::withMessages(['production' => 'The production stopped or moved to another part.']);
                }
            }

            $episode = $this->episodes->findLockedOrFail($episode->id);
            $assembly = $this->assemblies->lockedWithMedia($assembly->id);

            if ($assembly->status === AssemblyStatus::ACCEPTED && $episode->accepted_assembly_id === $assembly->id) {
                return $assembly;
            }

            if (! $project->is_active || $series->cycle_paused_at !== null || ! $actor->hasVerifiedEmail() || $series->archived_at !== null || $assembly->status !== AssemblyStatus::READY
                || $assembly->lock_version !== $data->lock_version || $episode->current_revision_id !== $revision->id
                || $revision->status !== RevisionStatus::APPROVED || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version
                || ! $assembly->getFirstMedia(EpisodeAssembly::VIDEO) instanceof Media) {
                throw ValidationException::withMessages(['assembly' => 'Only a ready assembly of the current approved screenplay and story context can be accepted.']);
            }

            $previous = $episode->predecessor_episode_id === null ? null : $this->episodes->forSeries($series->id, $episode->predecessor_episode_id);

            if (($previous instanceof Episode && ($previous->validity !== EpisodeValidity::CURRENT
                || $previous->accepted_assembly_id === null || $previous->accepted_assembly_id !== $revision->source_assembly_id))
                || (! $previous instanceof Episode && $revision->source_assembly_id !== null)) {
                throw ValidationException::withMessages(['predecessor' => 'The preceding accepted episode changed. Prepare a new screenplay version.']);
            }

            $this->assemblies->update($assembly, [
                'status' => AssemblyStatus::ACCEPTED,
                'continuity_snapshot' => [
                    'schema_version' => 1,
                    'confirmed_events' => $data->confirmed_events,
                    'character_state' => $data->character_state,
                    'open_threads' => $data->open_threads,
                    'scene_closed' => $data->scene_closed,
                    'source_assembly_id' => $assembly->id,
                    'episode_number' => $episode->number,
                    'duration_ms' => $assembly->duration_ms,
                ],
                'accepted_at' => now(),
                'accepted_by' => $actor->id,
                'lock_version' => $assembly->lock_version + 1,
            ]);
            $this->episodes->update($episode, [
                'accepted_assembly_id' => $assembly->id,
                'validity' => EpisodeValidity::CURRENT,
                'lock_version' => $episode->lock_version + 1,
            ]);
            $this->episodes->invalidateLater($episode, $series->id);

            $this->series->update($series, [
                'canonical_assembly_id' => $assembly->id,
                'continuity_version' => $series->continuity_version + 1,
            ]);

            return $assembly;
        });
    }
}
