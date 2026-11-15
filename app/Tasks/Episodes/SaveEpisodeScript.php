<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Classes\Story\Snapshot;
use App\Data\Episodes\EpisodeScriptData;
use App\Enums\AssemblyStatus;
use App\Enums\RevisionStatus;
use App\Enums\SeriesAudioMode;
use App\Enums\TransitionMode;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeAssembly;
use App\Models\EpisodeRevision;
use App\Models\Prompt;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEpisodeScript
{
    /** @param array<string, mixed> $context */
    public function run(Episode $episode, EpisodeScriptData $script, int $expectedVersion, array $context): EpisodeRevision
    {
        return DB::transaction(function () use ($episode, $script, $expectedVersion, $context): EpisodeRevision {
            $series = Series::query()->lockForUpdate()->findOrFail($episode->series_id);
            $episode = Episode::query()->lockForUpdate()->findOrFail($episode->id);

            if ($episode->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['lock_version' => 'The episode changed. Reload it before saving.']);
            }

            if ($series->archived_at !== null || $series->continuity_version !== ($context['continuity_version'] ?? null)
                || $series->specification_version !== ($context['specification_version'] ?? null)) {
                throw ValidationException::withMessages(['context' => 'The story context changed. Prepare a new draft.']);
            }

            $characters = CharacterRevision::query()
                ->whereIn('id', $script->character_revision_ids)
                ->where('status', RevisionStatus::APPROVED)
                ->whereHas('character', fn($query) => $query->where('series_id', $series->id)->whereNull('archived_at'))
                ->get();

            if ($characters->count() !== count($script->character_revision_ids)) {
                throw ValidationException::withMessages(['script.character_revision_ids' => 'Select approved characters from this series.']);
            }

            $shotCount = collect($script->scenes)->sum(fn($scene): int => count($scene->shots));
            $duration = collect($script->scenes)->flatMap(fn($scene): array => $scene->shots)->sum('duration_ms');

            if ($shotCount > 100 || $duration > 300000) {
                throw ValidationException::withMessages(['script.scenes' => 'An episode supports at most 100 shots and five minutes.']);
            }

            $characterIds = $characters->pluck('character_id')->all();
            $sourceIds = collect($script->scenes)->flatMap(fn($scene): array => $scene->shots)->pluck('source_prompt_id')->filter()->unique()->values()->all();
            $sources = Prompt::query()->whereIn('id', $sourceIds)->where('generated', true)
                ->whereHas('shot.scene.episodeRevision', fn($query) => $query->where('episode_id', $episode->id))
                ->with('shot.scene')->get()->keyBy('id');

            foreach ($script->scenes as $scenePosition => $scene) {
                foreach ($scene->shots as $shotPosition => $shot) {
                    if (data_get($context, 'specification.audio_mode') === SeriesAudioMode::SILENT->value && $shot->dialogue !== []) {
                        throw ValidationException::withMessages(['script.scenes.' . $scenePosition . '.shots.' . $shotPosition . '.dialogue' => 'Silent series cannot contain dialogue. Remove the lines or select speech and sound in the series brief.']);
                    }

                    if ($shot->source_prompt_id !== null && $shot->source_assembly_id !== null) {
                        throw ValidationException::withMessages(['script.scenes' => 'Choose one continuation source.']);
                    }

                    $source = $shot->source_prompt_id === null ? null : $sources->get($shot->source_prompt_id);

                    if ($shot->source_prompt_id !== null && ($source?->shot?->scene === null
                        || [$source->shot->scene->position, $source->shot->position] >= [$scenePosition + 1, $shotPosition + 1])) {
                        throw ValidationException::withMessages(['script.scenes' => 'A continuation take must be ready and belong to this episode. Use an accepted assembly to continue an earlier episode.']);
                    }

                    if ($shot->source_assembly_id !== null && ! EpisodeAssembly::query()
                        ->where('status', AssemblyStatus::ACCEPTED)
                        ->whereHas('episodeRevision.episode', fn($query) => $query->where('series_id', $series->id)->where('number', '<=', $episode->number))
                        ->whereKey($shot->source_assembly_id)
                        ->exists()) {
                        throw ValidationException::withMessages(['script.scenes' => 'A continuation assembly must be accepted in this series.']);
                    }

                    if ($scenePosition === 0 && $shotPosition === 0 && $shot->transition_mode === TransitionMode::CONTINUE_SCENE
                        && $shot->source_prompt_id === null && ($context['source_assembly_id'] ?? null) === null
                        && $shot->source_assembly_id === null) {
                        throw ValidationException::withMessages(['script.scenes' => 'The first continuous shot requires an accepted source.']);
                    }

                    if ($scenePosition === 0 && $shotPosition === 0 && $shot->transition_mode === TransitionMode::CONTINUE_SCENE
                        && data_get($context, 'continuity.scene_closed') === true) {
                        throw ValidationException::withMessages(['script.scenes' => 'The preceding scene is closed. Start a new scene or change the confirmed ending.']);
                    }

                    foreach ($shot->dialogue as $line) {
                        if (! in_array($line->character_id, $characterIds, true)) {
                            throw ValidationException::withMessages(['script.scenes' => 'Every dialogue speaker must belong to the pinned characters.']);
                        }
                    }
                }
            }

            $context['characters'] = $characters->map(fn(CharacterRevision $character): array => [
                'id' => $character->id, 'character_id' => $character->character_id,
                'description' => $character->description, 'appearance' => $character->appearance, 'voice' => $character->voice,
            ])->all();

            $maximumNumber = $episode->revisions()->max('number');
            $revision = $episode->revisions()->create([
                'number' => (is_numeric($maximumNumber) ? (int) $maximumNumber : 0) + 1,
                'status' => RevisionStatus::DRAFT,
                'title' => $script->title,
                'objective' => $script->objective,
                'context_snapshot' => $context,
                'context_hash' => Snapshot::hash($context),
                'source_assembly_id' => $context['source_assembly_id'] ?? null,
                'base_continuity_version' => $series->continuity_version,
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

            $episode->update(['title' => $script->title, 'current_revision_id' => $revision->id, 'lock_version' => $expectedVersion + 1]);

            return $revision;
        });
    }
}
