<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\RevisionStatus;
use App\Models\EpisodeRevision;
use App\Models\User;
use App\Repositories\EpisodeRepository;
use App\Repositories\EpisodeRevisionRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveEpisodeScript
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly EpisodeRevisionRepository $episodeRevisions,
        private readonly SeriesRepository $series,
        private readonly UserRepository $users,
    ) {}

    public function run(EpisodeRevision $revision, User $actor, bool $useGeneratedTitle = false): EpisodeRevision
    {
        $actor = $this->users->fresh($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        return DB::transaction(function () use ($revision, $actor, $useGeneratedTitle): EpisodeRevision {
            $episode = $this->episodes->findOrFail($revision->episode_id);
            $series = $this->series->findLockedOrFail($episode->series_id);
            $episode = $this->episodes->findLockedOrFail($episode->id);
            $revision = $this->episodeRevisions->findLockedOrFail($revision->id);

            if ($revision->status === RevisionStatus::APPROVED) {
                return $revision;
            }

            if ($revision->status !== RevisionStatus::DRAFT || $episode->current_revision_id !== $revision->id
                || $series->archived_at !== null || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version) {
                throw ValidationException::withMessages(['script' => 'Only the current draft with an up-to-date story context can be approved.']);
            }

            $episode->setRelation('series', $series);
            $revision->setRelation('episode', $episode);

            $this->episodeRevisions->update($revision, [
                'status' => RevisionStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);

            if ($useGeneratedTitle) {
                $this->episodes->update($episode, ['title' => $revision->title]);
            }

            return $revision;
        });
    }
}
