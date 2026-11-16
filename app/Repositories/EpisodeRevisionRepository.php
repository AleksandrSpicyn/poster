<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Story\Snapshot;
use App\Data\Episodes\EpisodeScriptData;
use App\Enums\RevisionStatus;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;

/** @extends EloquentRepository<EpisodeRevision> */
readonly class EpisodeRevisionRepository extends EloquentRepository
{
    protected function model(): string
    {
        return EpisodeRevision::class;
    }

    /** @param array<string, mixed> $context */
    public function createScript(Episode $episode, EpisodeScriptData $script, array $context, int $continuityVersion): EpisodeRevision
    {
        $maximumNumber = $episode->revisions()->max('number');
        $revision = $episode->revisions()->create([
            'number' => (is_numeric($maximumNumber) ? (int) $maximumNumber : 0) + 1,
            'status' => RevisionStatus::DRAFT,
            'title' => $script->title,
            'objective' => $script->objective,
            'context_snapshot' => $context,
            'context_hash' => Snapshot::hash($context),
            'source_assembly_id' => $context['source_assembly_id'] ?? null,
            'base_continuity_version' => $continuityVersion,
        ]);
        $revision->characterRevisions()->sync($script->character_revision_ids);

        foreach ($script->scenes as $scenePosition => $scene) {
            $savedScene = $revision->scenes()->create([
                'position' => $scenePosition + 1, 'title' => $scene->title,
                'setting' => ['location' => $scene->location, 'time' => $scene->time],
                'objective' => $scene->objective,
                'initial_state' => ['description' => $scene->initial_state],
                'expected_final_state' => ['description' => $scene->expected_final_state],
                'is_closed' => $scene->is_closed,
            ]);

            foreach ($scene->shots as $shotPosition => $shot) {
                $savedScene->shots()->create([
                    'position' => $shotPosition + 1, 'duration_ms' => $shot->duration_ms,
                    'action' => $shot->action, 'camera' => ['description' => $shot->camera],
                    'dialogue' => collect($shot->dialogue)->map(fn($line): array => $line->toArray())->all(),
                    'transition_mode' => $shot->transition_mode,
                    'source_prompt_id' => $shot->source_prompt_id,
                    'source_assembly_id' => $shot->source_assembly_id,
                    'source_anchor' => ['time_ms' => $shot->source_time_ms],
                ]);
            }
        }

        return $revision;
    }

    public function forAssembly(EpisodeRevision $revision): EpisodeRevision
    {
        return $revision->load('episode.series', 'scenes.shots');
    }

    public function forAssemblyRecord(EpisodeAssembly $assembly): EpisodeRevision
    {
        return $assembly->episodeRevision()->firstOrFail();
    }

    public function withScreenplay(EpisodeRevision $revision): EpisodeRevision
    {
        return $revision->loadMissing('scenes.shots');
    }
}
