<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\AcceptEpisodeData;
use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Enums\RevisionStatus;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AcceptEpisode
{
    public function run(EpisodeAssembly $assembly, AcceptEpisodeData $data, User $actor): EpisodeAssembly
    {
        $actor = $actor->fresh();

        if ($actor === null) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        return DB::transaction(function () use ($assembly, $data, $actor): EpisodeAssembly {
            $revision = EpisodeRevision::query()->findOrFail($assembly->episode_revision_id);
            $episode = Episode::query()->findOrFail($revision->episode_id);
            $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);
            $episode = Episode::query()->lockForUpdate()->findOrFail($episode->id);
            $assembly = EpisodeAssembly::query()->lockForUpdate()->findOrFail($assembly->id);

            if ($assembly->status === AssemblyStatus::ACCEPTED && $episode->accepted_assembly_id === $assembly->id) {
                return $assembly;
            }

            if (! $actor->hasVerifiedEmail() || $series->archived_at !== null || $assembly->status !== AssemblyStatus::READY
                || $assembly->lock_version !== $data->lock_version || $episode->current_revision_id !== $revision->id
                || $revision->status !== RevisionStatus::APPROVED || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version
                || $assembly->getFirstMedia(EpisodeAssembly::VIDEO) === null) {
                throw ValidationException::withMessages(['assembly' => 'Only a ready assembly of the current approved screenplay and story context can be accepted.']);
            }

            $previous = $episode->predecessor_episode_id === null ? null : Episode::query()
                ->where('series_id', $series->id)
                ->findOrFail($episode->predecessor_episode_id);

            if (($previous !== null && ($previous->validity !== EpisodeValidity::CURRENT
                || $previous->accepted_assembly_id === null || $previous->accepted_assembly_id !== $revision->source_assembly_id))
                || ($previous === null && $revision->source_assembly_id !== null)) {
                throw ValidationException::withMessages(['predecessor' => 'The preceding accepted episode changed. Prepare a new screenplay version.']);
            }

            $assembly->update([
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
                'accepted_at' => now(), 'accepted_by' => $actor->id,
                'lock_version' => $assembly->lock_version + 1,
            ]);
            $episode->update([
                'accepted_assembly_id' => $assembly->id,
                'validity' => EpisodeValidity::CURRENT,
                'lock_version' => $episode->lock_version + 1,
            ]);
            Episode::query()->where('series_id', $series->id)->where('number', '>', $episode->number)
                ->update(['validity' => EpisodeValidity::REQUIRES_REVIEW->value, 'lock_version' => DB::raw('lock_version + 1')]);
            $series->update([
                'canonical_assembly_id' => $assembly->id,
                'continuity_version' => $series->continuity_version + 1,
            ]);

            return $assembly;
        });
    }
}
