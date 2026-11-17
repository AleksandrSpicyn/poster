<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\EpisodeScriptData;
use App\Data\Episodes\ShotData;
use App\Enums\SeriesAudioMode;
use App\Enums\SeriesOutputMode;
use App\Enums\TransitionMode;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\EpisodeRevision;
use App\Repositories\CharacterRevisionRepository;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\EpisodeRepository;
use App\Repositories\EpisodeRevisionRepository;
use App\Repositories\PromptRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEpisodeScript
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly SeriesRepository $series,
        private readonly EpisodeRevisionRepository $episodeRevisions,
        private readonly CharacterRevisionRepository $characterRevisions,
        private readonly PromptRepository $prompts,
        private readonly EpisodeAssemblyRepository $assemblies,
    ) {}

    /** @param array<string, mixed> $context */
    public function run(Episode $episode, EpisodeScriptData $script, int $expectedVersion, array $context): EpisodeRevision
    {
        return DB::transaction(function () use ($episode, $script, $expectedVersion, $context): EpisodeRevision {
            $series = $this->series->findLockedOrFail($episode->series_id);
            $episode = $this->episodes->findLockedOrFail($episode->id);

            if ($episode->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['lock_version' => 'The episode changed. Reload it before saving.']);
            }

            if ($series->archived_at !== null || $series->continuity_version !== ($context['continuity_version'] ?? null)
                || $series->specification_version !== ($context['specification_version'] ?? null)) {
                throw ValidationException::withMessages(['context' => 'The story context changed. Prepare a new draft.']);
            }

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO) {
                if (($series->specification['scene_mode'] ?? null) === 'same_scene') {
                    $allShots = collect($script->scenes)->flatMap(fn($scene): array => $scene->shots)->values();

                    foreach ($allShots as $index => $cycleShot) {
                        if (($episode->number > 1 || $index > 0) && $cycleShot->transition_mode === TransitionMode::NEW_SCENE) {
                            throw ValidationException::withMessages(['script.scenes' => 'This cycle keeps one scene; an additional new_scene is not allowed.']);
                        }
                    }
                }

                $part = data_get($context, 'cycle.part');
                $first = $script->scenes[0]->shots[0];

                if (! is_array($part) || $first->transition_mode->value !== ($part['transition_mode'] ?? null)) {
                    throw ValidationException::withMessages(['script.scenes' => 'The entry transition must match the pinned cycle boundary.']);
                }

                $total = collect($script->scenes)->flatMap(fn($scene) => $scene->shots)->sum('duration_ms');

                $partDuration = $series->specification['episode_duration_seconds'] ?? null;

                if (! is_int($partDuration) || $total !== $partDuration * 1000) {
                    throw ValidationException::withMessages(['script.scenes' => 'The part must match the pinned cycle duration.']);
                }
            }

            $characters = $this->characterRevisions->approvedSelection($series, $script);

            if ($characters->count() !== count($script->character_revision_ids)) {
                throw ValidationException::withMessages(['script.character_revision_ids' => 'Select approved characters from this series.']);
            }

            $shotCount = collect($script->scenes)->sum(fn($scene): int => count($scene->shots));
            $duration = collect($script->scenes)->flatMap(fn($scene): array => $scene->shots)->sum('duration_ms');

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO && $shotCount + $this->episodes->otherCycleShotCount($episode) > 100) {
                throw ValidationException::withMessages(['script.scenes' => 'The complete cycle supports at most 100 shots.']);
            }

            if ($shotCount > 100 || $duration > 300000) {
                throw ValidationException::withMessages(['script.scenes' => 'An episode supports at most 100 shots and five minutes.']);
            }

            $characterIds = $characters->pluck('character_id')->all();
            $sourceIds = array_values(collect($script->scenes)->flatMap(fn($scene): array => $scene->shots)->map(fn(ShotData $shot): ?int => $shot->source_prompt_id)->filter()->unique()->values()->all());
            $sources = $this->prompts->readySources($episode, $sourceIds);

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

                    if ($shot->source_assembly_id !== null && ! $this->assemblies->isAcceptedSource($shot->source_assembly_id, $series->id, $episode->number)) {
                        throw ValidationException::withMessages(['script.scenes' => 'A continuation assembly must be accepted in this series.']);
                    }

                    if ($scenePosition === 0 && $shotPosition === 0 && $shot->transition_mode === TransitionMode::CONTINUE_SCENE
                        && $shot->source_prompt_id === null && ($context['source_assembly_id'] ?? null) === null
                        && $shot->source_assembly_id === null) {
                        throw ValidationException::withMessages(['script.scenes' => 'The first continuous shot requires an accepted source.']);
                    }

                    if ($scenePosition === 0 && $shotPosition === 0 && $shot->transition_mode === TransitionMode::CONTINUE_SCENE
                        && data_get($context, 'continuity.scene_closed') === true && ! isset($context['cycle'])) {
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

            $revision = $this->episodeRevisions->createScript($episode, $script, $context, $series->continuity_version);

            $this->episodes->update($episode, ['title' => $script->title, 'current_revision_id' => $revision->id, 'lock_version' => $expectedVersion + 1]);

            return $revision;
        });
    }
}
