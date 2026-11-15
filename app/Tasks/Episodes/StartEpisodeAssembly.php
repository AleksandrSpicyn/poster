<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Enums\RevisionStatus;
use App\Models\EpisodeAssembly;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartEpisodeAssembly
{
    public function run(EpisodeAssembly $assembly): ?EpisodeAssembly
    {
        return DB::transaction(function () use ($assembly): ?EpisodeAssembly {
            $assembly = EpisodeAssembly::query()->lockForUpdate()->findOrFail($assembly->id);

            if (in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
                return null;
            }

            if ($assembly->status === AssemblyStatus::BUILDING && $assembly->started_at?->greaterThan(now()->subSeconds(3720))) {
                return null;
            }

            $revision = $assembly->episodeRevision()->firstOrFail();
            $episode = $revision->episode()->firstOrFail();
            $series = $episode->series()->firstOrFail();

            if ($series->archived_at !== null || $episode->current_revision_id !== $revision->id
                || $revision->status !== RevisionStatus::APPROVED
                || $revision->base_continuity_version !== $series->continuity_version
                || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version) {
                throw ValidationException::withMessages(['assembly' => 'The screenplay or story context changed. Prepare a new assembly.']);
            }

            $assembly->update([
                'status' => AssemblyStatus::BUILDING, 'started_at' => now(), 'completed_at' => null,
                'error_message' => null, 'lock_version' => $assembly->lock_version + 1,
            ]);

            return $assembly->refresh();
        });
    }
}
