<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Data\Shots\PinnedCharacterData;
use App\Data\Shots\PinnedReferenceData;
use App\Data\Shots\ShotContinuationData;
use App\Data\Shots\ShotVideoSettingsData;
use App\Enums\SeriesAudioMode;
use App\Enums\TransitionMode;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use RuntimeException;

readonly class PrepareShotGenerationInput
{
    /**
     * @param list<PinnedCharacterData> $characters
     * @return array<string, mixed>
     */
    public function run(Shot $shot, User $actor, ShotVideoSettingsData $settings, array $characters, ShotContinuationData $continuation): array
    {
        $scene = $shot->scene ?? throw new RuntimeException('Shot scene is missing.');
        $revision = $scene->episodeRevision ?? throw new RuntimeException('Shot screenplay is missing.');
        $episode = $revision->episode ?? throw new RuntimeException('Shot episode is missing.');
        $series = $episode->series ?? throw new RuntimeException('Shot series is missing.');
        $duration = $settings->duration;
        $audioMode = $settings->audioMode;
        $hasDialogue = $shot->dialogue !== [];
        $sourceMediaId = $continuation->sourceMediaId;
        $sourceChecksum = $continuation->sourceChecksum;
        $sourcePromptId = $continuation->sourcePromptId;
        $sourceAssemblyId = $continuation->sourceAssemblyId;

        $primaryReferences = collect($characters)->map(fn(PinnedCharacterData $character): int => $character->references[0]->media_id)->all();
        $secondaryReferences = collect($characters)->flatMap(fn(PinnedCharacterData $character): array => array_slice($character->references, 1))
            ->map(fn(PinnedReferenceData $reference): int => $reference->media_id)->all();

        $sourceReferencePosition = $sourceMediaId !== null && $shot->transition_mode === TransitionMode::NEW_SHOT ? count($primaryReferences) : null;
        $availableReferences = $sourceReferencePosition === null ? 10 : 9;

        if (count($primaryReferences) > $availableReferences) {
            throw ValidationException::withMessages(['characters' => 'A continuity reference leaves room for at most nine character identities.']);
        }

        $referenceMediaIds = [...$primaryReferences, ...array_slice($secondaryReferences, 0, $availableReferences - count($primaryReferences))];
        $specification = json_encode(collect($series->specification)->except('story_outline')->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $characterPrompt = json_encode(collect($characters)->map(fn(PinnedCharacterData $character): array => [
            'id' => $character->id, 'character_id' => $character->character_id, 'description' => $character->description,
            'name' => $character->name,
            'appearance' => $character->appearance, 'voice' => $character->voice,
        ])->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $setting = json_encode($scene->setting, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $cameraSettings = $shot->camera;

        if ($shot->plan_type !== null) {
            $cameraSettings['plan_type'] = $shot->plan_type;
        }

        $camera = json_encode($cameraSettings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $dialogue = json_encode($shot->dialogue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $initialState = json_encode($scene->initial_state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $finalState = $shot->position === $scene->shots->max('position')
            ? 'This is the last shot of the scene. Reach this visible state by the end: ' . json_encode($scene->expected_final_state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            : 'This is an intermediate shot. Perform only its scripted action; do not skip forward to the scene resolution.';
        $sound = match (true) {
            $audioMode === SeriesAudioMode::SILENT => 'Completely silent video. No speech, narration, music, singing, sound effects or any other audio.',
            $hasDialogue => "Speak only these exact dialogue lines in the series language, using each character's pinned voice blueprint and delivery: $dialogue. Keep voices distinct and consistent. Do not narrate the production instructions.",
            default => 'No speech, narration, commentary, singing or intelligible vocals. Natural action sounds and suitable instrumental music only.',
        };
        $seriesVoice = $revision->context_snapshot['series_voice'] ?? null;
        $lead = $revision->context_snapshot['lead_character_id'] ?? null;

        if ($audioMode !== SeriesAudioMode::SILENT && $hasDialogue && is_string($seriesVoice) && $seriesVoice !== '') {
            if ($lead === null) {
                $sound .= " Series voice direction for the scripted lines: $seriesVoice.";
            } elseif (is_int($lead) && in_array($lead, array_column($shot->dialogue, 'character_id'), true)) {
                $sound .= " For character $lead, apply the series voice direction: $seriesVoice. Keep other characters' individual voice blueprints.";
            }
        }

        $imagePrompt = <<<TEXT
Create the opening frame of this scripted shot using the supplied character references.
Series world, style and constraints (not additional events to depict): $specification
Pinned character appearance and voice versions: $characterPrompt
Scene setting: $setting
Scene objective: {$scene->objective}
Scene opening state: $initialState
Camera: $camera
Action to start, not its completed outcome: {$shot->action}
Stage only the first instant of this action with the composition, relationships and tension specified by the shot. If a physical goal or obstacle is scripted, make it readable and leave space for the required movement; do not invent physical obstacles or props for a dialogue-driven or contemplative scene. Do not combine successive beats into one image or show a later result already achieved. A supplied previous-scene frame is the current physical state and takes precedence over the scene opening state when continuing or changing camera.
Use the series visual style, including animation when specified. Keep each referenced character recognisable. Character references define identity, not a requirement to repeat their pose, background or previous activity. Preserve the number and attachment of permanent accessories unless a change is explicitly required at this instant; do not accidentally turn a worn detail into a second loose prop. Include only props and characters needed by this shot, without adding activities from the series premise or personality description. Output one portrait 9:16 frame, with no text, captions, logos or watermark.
TEXT;
        $videoPrompt = <<<TEXT
Produce this scripted {$duration}-second shot.
Series world, style and constraints (not additional events to enact): $specification
Pinned character versions: $characterPrompt
Scene: $setting
Scene objective: {$scene->objective}
Scene opening state (background context): $initialState
Camera: $camera
Action: {$shot->action}
Shot endpoint: $finalState
Transition: {$shot->transition_mode->value}. Begin directly from the supplied first frame; its visible state takes precedence over an earlier scene opening state. Preserve appearance, clothing, object positions and coherent motion except for the changes explicitly scripted. Execute the beats in the intended order so each develops the action, relationship, knowledge or tension required by the scene. Dialogue and stillness can carry the progression; do not replace them with an invented physical gag. Allocate time for the specified result and hold it long enough to read. Do not add an unplanned gesture loop, reset, reversal or mystery, or substitute staring and smiling for required events. Respect a deliberate story loop, ambiguous ending or cliffhanger when explicitly scripted; do not impose a resolution on an unfinished scene.
Preserve the identity and number of props except where the action explicitly calls for a transformation, duplication, disappearance, destruction, consumption or teleportation. Show such changes as part of the intended causal action, not as unexplained continuity errors. Permanent accessories stay attached unless the script requires otherwise. The series premise and character descriptions guide style and identity; they do not authorize extra events or recurring props absent from this shot.
Sound: $sound
No extra dialogue, printed captions, logos or watermark. Do not replace the series style with an unrelated style.
TEXT;

        return [
            'schema_version' => 1, 'actor_id' => $actor->id, 'series_id' => $series->id,
            'episode_revision_id' => $revision->id, 'specification_version' => $series->specification_version,
            'continuity_version' => $series->continuity_version, 'context_hash' => $revision->context_hash,
            'characters' => collect($characters)->map(fn(PinnedCharacterData $character): array => $character->toArray())->all(), 'transition_mode' => $shot->transition_mode->value,
            'audio_mode' => $audioMode->value,
            'reference_media_ids' => $referenceMediaIds, 'source_reference_position' => $sourceReferencePosition,
            'source_media_id' => $sourceMediaId, 'source_sha256' => $sourceChecksum,
            'source_prompt_id' => $sourcePromptId, 'source_assembly_id' => $sourceAssemblyId,
            'source_time_ms' => $shot->source_anchor['time_ms'] ?? null,
            'image_prompt' => $imagePrompt, 'video_prompt' => $videoPrompt,
            'video' => $settings->videoSnapshot(),
        ];
    }
}
