<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\EpisodeValidity;
use App\Enums\RevisionStatus;
use App\Models\Shot;
use Illuminate\Validation\ValidationException;

class AssertShotGenerationContext
{
    public function run(Shot $shot): Shot
    {
        $shot = Shot::query()->with(['scene.episodeRevision.episode.series.project', 'scene.episodeRevision.characterRevisions.character', 'scene.episodeRevision.characterRevisions.media'])->findOrFail($shot->id);
        $revision = $shot->scene?->episodeRevision;
        $episode = $revision?->episode;
        $series = $episode?->series;

        if ($revision === null || $episode === null || $series === null
            || $revision->status !== RevisionStatus::APPROVED || $episode->current_revision_id !== $revision->id
            || $episode->validity !== EpisodeValidity::CURRENT || $series->archived_at !== null
            || $series->project?->is_active !== true
            || $revision->base_continuity_version !== $series->continuity_version
            || data_get($revision->context_snapshot, 'specification_version') !== $series->specification_version) {
            throw ValidationException::withMessages(['shot' => 'Generate shots only from the current approved screenplay with an up-to-date story context.']);
        }

        if ($shot->duration_ms % 1000 !== 0) {
            throw ValidationException::withMessages(['duration_ms' => 'The selected video models require whole-second shot durations.']);
        }

        return $shot;
    }
}
