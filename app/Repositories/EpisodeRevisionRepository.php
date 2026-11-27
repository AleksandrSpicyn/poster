<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Classes\Story\Snapshot;
use App\Data\Episodes\EpisodeScriptData;
use App\Enums\RevisionStatus;
use App\Events\WorkspaceUpdated;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Scene;
use App\Models\Shot;
use LogicException;

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
        $revision = $episode->revisions()->chaperone('episode')->create([
            'number' => (is_numeric($maximumNumber) ? (int) $maximumNumber : 0) + 1,
            'status' => RevisionStatus::DRAFT,
            'title' => $script->title,
            'objective' => $script->objective,
            'public_caption' => $script->public_caption,
            'context_snapshot' => $context,
            'context_hash' => Snapshot::hash($context),
            'source_assembly_id' => $context['source_assembly_id'] ?? null,
            'base_continuity_version' => $continuityVersion,
        ]);
        $revision->characterRevisions()->attach(array_values(array_unique($script->character_revision_ids)));

        $scenes = [];

        foreach ($script->scenes as $position => $scene) {
            $scenes[] = [
                'episode_revision_id' => $revision->id,
                'position' => $position + 1,
                'title' => $scene->title,
                'setting' => ['location' => $scene->location, 'time' => $scene->time],
                'objective' => $scene->objective,
                'initial_state' => ['description' => $scene->initial_state],
                'expected_final_state' => ['description' => $scene->expected_final_state],
                'is_closed' => $scene->is_closed,
            ];
        }

        Scene::query()->fillAndInsert($scenes);
        $savedScenes = $revision->scenes()->chaperone('episodeRevision')->get([
            'id',
            'position',
            'episode_revision_id',
        ])->keyBy('position');
        $shots = [];

        foreach ($script->scenes as $position => $scene) {
            $saved = $savedScenes->get($position + 1) ?? throw new LogicException('Saved scene missing.');

            foreach ($scene->shots as $shotPosition => $shot) {
                $shots[] = [
                    'scene_id' => $saved->id,
                    'position' => $shotPosition + 1,
                    'duration_ms' => $shot->duration_ms,
                    'title' => $shot->title,
                    'plan_type' => $shot->plan_type,
                    'action' => $shot->action,
                    'camera' => $shot->direction === null ? ['description' => $shot->camera] : ['description' => $shot->camera, 'direction' => $shot->direction->toArray()],
                    'dialogue' => collect($shot->dialogue)->map(fn($line): array => array_filter($line->toArray(), static fn(mixed $value): bool => $value !== null))->all(),
                    'transition_mode' => $shot->transition_mode,
                    'source_prompt_id' => $shot->source_prompt_id,
                    'source_assembly_id' => $shot->source_assembly_id,
                    'source_anchor' => ['time_ms' => $shot->source_time_ms],
                ];
            }
        }

        foreach (array_chunk($shots, 50) as $batch) {
            Shot::query()->fillAndInsert($batch);
        }

        $savedScenes->load(['shots' => fn($query) => $query->chaperone('scene')->select('id', 'scene_id')]);

        foreach ($savedScenes as $saved) {
            WorkspaceUpdated::forResource($saved);

            foreach ($saved->shots as $savedShot) {
                WorkspaceUpdated::forResource($savedShot);
            }
        }

        return $revision;
    }

    public function forAssembly(EpisodeRevision $revision): EpisodeRevision
    {
        return $revision->loadMissing('episode.series')->load('scenes.shots');
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
