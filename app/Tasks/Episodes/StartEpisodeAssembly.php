<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Enums\RevisionStatus;
use App\Models\EpisodeAssembly;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\EpisodeRevisionRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartEpisodeAssembly
{
    public function __construct(
        private readonly EpisodeAssemblyRepository $assemblies,
        private readonly ProjectRepository $projects,
        private readonly EpisodeRepository $episodes,
        private readonly SeriesRepository $series,
        private readonly UserRepository $users,
        private readonly EpisodeRevisionRepository $episodeRevisions,
    ) {}

    public function run(EpisodeAssembly $assembly): ?EpisodeAssembly
    {
        return DB::transaction(function () use ($assembly): ?EpisodeAssembly {
            $series = $this->series->forAssembly($assembly);
            $project = $this->projects->findLockedOrFail($series->project_id);
            $assembly = $this->assemblies->findLockedOrFail($assembly->id);

            if (in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
                return null;
            }

            if ($assembly->status === AssemblyStatus::BUILDING && $assembly->started_at?->greaterThan(now()->subSeconds(3720))) {
                return null;
            }

            $revision = $this->episodeRevisions->forAssemblyRecord($assembly);
            $episode = $this->episodes->forRevision($revision);
            $series = $this->series->forEpisode($episode);

            if ($project->owner_id === null || $revision->approved_by !== $project->owner_id || ! $this->users->isVerified($project->owner_id)
                || ! $project->is_active || $series->archived_at !== null || $episode->current_revision_id !== $revision->id
                || $revision->status !== RevisionStatus::APPROVED
                || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version) {
                throw ValidationException::withMessages(['assembly' => 'The screenplay or story context changed. Prepare a new assembly.']);
            }

            $this->assemblies->update($assembly, [
                'status' => AssemblyStatus::BUILDING, 'started_at' => now(), 'completed_at' => null,
                'error_message' => null, 'lock_version' => $assembly->lock_version + 1,
            ]);

            return $this->assemblies->refresh($assembly);
        });
    }
}
