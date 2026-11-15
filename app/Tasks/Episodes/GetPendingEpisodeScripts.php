<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\ScriptGenerationStatus;
use App\Events\WorkspaceUpdated;
use App\Models\Episode;
use Illuminate\Database\Eloquent\Collection;

class GetPendingEpisodeScripts
{
    /** @return Collection<int, Episode> */
    public function run(int $limit): Collection
    {
        $limit = max(1, min(100, $limit));
        $expiredIds = Episode::query()->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->where('script_started_at', '<', now()->subMinutes(10))
            ->orderBy('id')->limit($limit)->pluck('id');
        $updated = Episode::query()->whereKey($expiredIds)
            ->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->where('script_started_at', '<', now()->subMinutes(10))
            ->update(['script_generation_status' => ScriptGenerationStatus::OUTCOME_UNKNOWN, 'script_generation_error' => 'The worker stopped before confirming a result. Review before requesting another draft.']);

        if ($updated > 0) {
            WorkspaceUpdated::dispatch(true);
        }

        return Episode::query()->where('script_generation_status', ScriptGenerationStatus::QUEUED)
            ->orderBy('id')->limit($limit)->get();
    }
}
