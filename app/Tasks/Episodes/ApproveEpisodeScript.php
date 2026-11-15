<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\RevisionStatus;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Models\Series;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveEpisodeScript
{
    public function run(EpisodeRevision $revision, User $actor, bool $useGeneratedTitle = false): EpisodeRevision
    {
        $actor = $actor->fresh();

        if ($actor === null) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        return DB::transaction(function () use ($revision, $actor, $useGeneratedTitle): EpisodeRevision {
            $episode = Episode::query()->findOrFail($revision->episode_id);
            $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);
            $episode = Episode::query()->lockForUpdate()->findOrFail($episode->id);
            $revision = EpisodeRevision::query()->lockForUpdate()->findOrFail($revision->id);

            if ($revision->status === RevisionStatus::APPROVED) {
                return $revision;
            }

            if ($revision->status !== RevisionStatus::DRAFT || $episode->current_revision_id !== $revision->id
                || $series->archived_at !== null || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version) {
                throw ValidationException::withMessages(['script' => 'Only the current draft with an up-to-date story context can be approved.']);
            }

            $revision->update(['status' => RevisionStatus::APPROVED, 'approved_at' => now(), 'approved_by' => $actor->id]);

            if ($useGeneratedTitle) {
                $episode->update(['title' => $revision->title]);
            }

            return $revision;
        });
    }
}
