<?php

declare(strict_types=1);

namespace App\Classes\Story;

use App\Data\Episodes\DialogueLineData;
use App\Data\Episodes\EpisodeScriptData;
use App\Data\Episodes\ShotData;
use App\Enums\SeriesAudioMode;
use App\Enums\ShotCueKind;
use App\Enums\TransitionMode;
use App\Models\CharacterRevision;
use App\Models\Episode;
use App\Models\Prompt;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\PromptRepository;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class EpisodeShotRules
{
    public function __construct(private PromptRepository $prompts, private EpisodeAssemblyRepository $assemblies) {}

    /**
     * @param Collection<int, ShotData> $shots
     * @param Collection<int, CharacterRevision> $characters
     * @param array<string, mixed> $context
     */
    public function validate(Episode $episode, EpisodeScriptData $script, Collection $shots, Collection $characters, array $context): void
    {
        $characterIds = $characters->map(static fn(CharacterRevision $revision): int => $revision->character_id);
        $silent = data_get($context, 'specification.audio_mode') === SeriesAudioMode::SILENT->value;
        $sourceIds = array_values($shots->map(fn(ShotData $shot): ?int => $shot->source_prompt_id)->filter()->unique()->all());
        $sources = $this->prompts->readySources($episode, $sourceIds);

        $assemblyIds = $shots->map(fn(ShotData $shot): ?int => $shot->source_assembly_id)->filter()->unique()->values()->all();
        $acceptedIds = $this->assemblies->acceptedSourceIds($assemblyIds, $episode->series_id, $episode->number);

        foreach ($script->scenes as $scenePosition => $scene) {
            foreach ($scene->shots as $shotPosition => $shot) {
                $direction = $shot->direction;

                if ($direction !== null && array_diff($direction->visible_character_ids ?? [], $characterIds->all()) !== []) {
                    throw ValidationException::withMessages(['script.scenes' => 'Выберите участников кадра из состава эпизода.']);
                }

                foreach ($shot->dialogue as $line) {
                    if (($line->start_ms === null) !== ($line->end_ms === null)
                        || ($line->start_ms !== null && ($line->end_ms <= $line->start_ms || $line->end_ms > $shot->duration_ms))) {
                        throw ValidationException::withMessages(['script.scenes' => 'Проверьте начало и конец реплики внутри кадра.']);
                    }
                }

                foreach ($direction->cues ?? [] as $cue) {
                    if ($cue->end_ms <= $cue->start_ms || $cue->end_ms > $shot->duration_ms || ($cue->kind === ShotCueKind::TEXT && $cue->asset_id !== null)
                        || ($cue->kind === ShotCueKind::INSERT && $cue->asset_id === null) || ($silent && $cue->kind === ShotCueKind::SOUND)) {
                        throw ValidationException::withMessages(['script.scenes' => 'Проверьте тип и интервал звука или титра.']);
                    }
                }

                if ($silent && $shot->dialogue !== []) {
                    throw ValidationException::withMessages(['script.scenes.' . $scenePosition . '.shots.' . $shotPosition . '.dialogue' => 'Silent series cannot contain dialogue. Remove the lines or select speech and sound in the series brief.']);
                }

                $source = $shot->source_prompt_id === null ? null : $sources->get($shot->source_prompt_id);
                $this->validateContinuation($shot, $source, $acceptedIds, $scenePosition, $shotPosition, $context);

                if (collect($shot->dialogue)->contains(fn(DialogueLineData $line): bool => ! $characterIds->containsStrict($line->character_id))) {
                    throw ValidationException::withMessages(['script.scenes' => 'Every dialogue speaker must belong to the pinned characters.']);
                }
            }
        }
    }

    /**
     * @param list<int> $acceptedIds
     * @param array<string, mixed> $context
     */
    private function validateContinuation(ShotData $shot, ?Prompt $source, array $acceptedIds, int $scenePosition, int $shotPosition, array $context): void
    {
        $isOpeningContinuation = $scenePosition === 0 && $shotPosition === 0 && $shot->transition_mode === TransitionMode::CONTINUE_SCENE;

        if ($shot->source_prompt_id !== null && $shot->source_assembly_id !== null) {
            throw ValidationException::withMessages(['script.scenes' => 'Choose one continuation source.']);
        }

        if ($shot->source_prompt_id !== null && ($source?->shot?->scene === null
            || [$source->shot->scene->position, $source->shot->position] >= [$scenePosition + 1, $shotPosition + 1])) {
            throw ValidationException::withMessages(['script.scenes' => 'A continuation take must be ready and belong to this episode. Use an accepted assembly to continue an earlier episode.']);
        }

        if ($shot->source_assembly_id !== null && ! in_array($shot->source_assembly_id, $acceptedIds, true)) {
            throw ValidationException::withMessages(['script.scenes' => 'A continuation assembly must be accepted in this series.']);
        }

        if ($isOpeningContinuation
            && $shot->source_prompt_id === null && ($context['source_assembly_id'] ?? null) === null
            && $shot->source_assembly_id === null) {
            throw ValidationException::withMessages(['script.scenes' => 'The first continuous shot requires an accepted source.']);
        }

        if ($isOpeningContinuation
            && data_get($context, 'continuity.scene_closed') === true && ! isset($context['cycle'])) {
            throw ValidationException::withMessages(['script.scenes' => 'The preceding scene is closed. Start a new scene or change the confirmed ending.']);
        }
    }
}
