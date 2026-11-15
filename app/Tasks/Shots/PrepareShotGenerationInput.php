<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\VideoGenerationRequestData;
use App\Enums\AssemblyStatus;
use App\Enums\RevisionStatus;
use App\Enums\SeriesAudioMode;
use App\Enums\TransitionMode;
use App\Models\CharacterRevision;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Shot;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class PrepareShotGenerationInput
{
    public function __construct(private VideoGeneratorFactory $generators) {}

    /** @return array<string, mixed> */
    public function run(Shot $shot, User $actor, ?int $sourcePromptId): array
    {
        $scene = $shot->scene ?? throw new RuntimeException('Shot scene is missing.');
        $revision = $scene->episodeRevision ?? throw new RuntimeException('Shot screenplay is missing.');
        $episode = $revision->episode ?? throw new RuntimeException('Shot episode is missing.');
        $series = $episode->series ?? throw new RuntimeException('Shot series is missing.');
        $provider = $this->generators->primary();
        $generator = $this->generators->make($provider);
        $capabilities = $generator->capabilities();
        $duration = intdiv($shot->duration_ms, 1000);
        $hasDialogue = $shot->dialogue !== [];
        $audioModeValue = data_get($revision->context_snapshot, 'specification.audio_mode', $series->specification['audio_mode'] ?? SeriesAudioMode::NATIVE->value);
        $audioMode = is_string($audioModeValue) ? SeriesAudioMode::tryFrom($audioModeValue) : null;

        if ($audioMode === null) {
            throw ValidationException::withMessages(['audio_mode' => 'Select a supported sound mode in the series brief.']);
        }

        if ($audioMode === SeriesAudioMode::SILENT && $hasDialogue) {
            throw ValidationException::withMessages(['dialogue' => 'Silent series cannot contain dialogue.']);
        }

        $generateAudio = $audioMode === SeriesAudioMode::NATIVE && $capabilities->supportsAudioGeneration;

        if ($hasDialogue && ! $capabilities->supportsAudioGeneration) {
            throw ValidationException::withMessages(['dialogue' => 'This video model cannot generate dialogue. Select a model with native audio.']);
        }

        try {
            $capabilities->assertSupports(new VideoGenerationRequestData(
                prompt: $shot->action,
                duration: $duration,
                model: $capabilities->model,
                firstFrameUrl: 'pending-private-frame',
                resolution: $capabilities->defaultResolution,
                aspectRatio: $capabilities->defaultAspectRatio,
                returnLastFrame: $capabilities->supportsReturnLastFrame,
                generateAudio: $generateAudio,
            ));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['generation' => 'The configured video model does not support this shot duration, resolution or audio mode.']);
        }

        $characters = $revision->characterRevisions->map(function (CharacterRevision $character) use ($series): array {
            if ($character->status !== RevisionStatus::APPROVED || $character->character?->series_id !== $series->id) {
                throw ValidationException::withMessages(['characters' => 'Every character must have an approved version from this series.']);
            }

            $references = $character->getMedia(CharacterRevision::REFERENCES)->map(function (Media $media): array {
                $checksum = hash_file('sha256', $media->getPath());

                if ($checksum === false) {
                    throw ValidationException::withMessages(['references' => 'An approved reference file is missing.']);
                }

                return ['media_id' => $media->id, 'sha256' => $checksum];
            })->values()->all();

            if ($references === []) {
                throw ValidationException::withMessages(['characters' => 'Upload approved appearance references before generation.']);
            }

            return ['id' => $character->id, 'character_id' => $character->character_id, 'name' => $character->character->name, 'description' => $character->description,
                'appearance' => $character->appearance, 'voice' => $character->voice, 'references' => $references];
        })->values()->all();

        if ($characters === [] || count($characters) > 10) {
            throw ValidationException::withMessages(['characters' => 'A shot requires between one and ten pinned characters.']);
        }

        $sourceMediaId = null;
        $sourceChecksum = null;
        $sourcePromptId ??= $shot->source_prompt_id;
        $sourceAssemblyId = $shot->source_assembly_id ?? $revision->source_assembly_id;

        if ($shot->transition_mode !== TransitionMode::NEW_SCENE) {
            $media = null;

            if ($sourcePromptId !== null) {
                $source = Prompt::query()->with('shot.scene.episodeRevision')->findOrFail($sourcePromptId);
                $sourceShot = $source->shot;
                $sourceScene = $sourceShot?->scene;

                if (! $source->generated || $sourceShot === null || $sourceScene === null
                    || $sourceScene->episodeRevision?->episode_id !== $episode->id
                    || $sourceScene->position > $scene->position
                    || ($sourceScene->position === $scene->position && $sourceShot->position >= $shot->position)) {
                    throw ValidationException::withMessages(['source_prompt_id' => 'Select a ready take from an earlier shot of this episode.']);
                }

                if ($shot->transition_mode === TransitionMode::CONTINUE_SCENE && $sourceScene->is_closed) {
                    throw ValidationException::withMessages(['source_prompt_id' => 'The preceding scene is closed. Use a new scene or revise its closure explicitly.']);
                }

                $media = $source->getFirstMedia(Prompt::SHOT_VIDEO);
            } elseif ($sourceAssemblyId !== null) {
                $source = EpisodeAssembly::query()->with('episodeRevision.episode')->findOrFail($sourceAssemblyId);

                if ($source->status !== AssemblyStatus::ACCEPTED || $source->id !== $revision->source_assembly_id
                    || $source->episodeRevision?->episode?->series_id !== $series->id) {
                    throw ValidationException::withMessages(['source_assembly_id' => 'Select the accepted predecessor assembly pinned by this screenplay.']);
                }

                if ($shot->transition_mode === TransitionMode::CONTINUE_SCENE && ($source->continuity_snapshot['scene_closed'] ?? null) === true) {
                    throw ValidationException::withMessages(['source_assembly_id' => 'The accepted preceding scene is closed. Start a new scene.']);
                }

                $media = $source->getFirstMedia(EpisodeAssembly::VIDEO);
            }

            if ($shot->transition_mode === TransitionMode::CONTINUE_SCENE && $media === null) {
                throw ValidationException::withMessages(['source_prompt_id' => 'A continued scene requires an earlier ready take or an accepted predecessor video.']);
            }

            if ($media !== null) {
                $sourceMediaId = $media->id;
                $sourceChecksum = hash_file('sha256', $media->getPath());

                if ($sourceChecksum === false) {
                    throw ValidationException::withMessages(['source' => 'The continuation source file is missing.']);
                }
            }
        }

        $primaryReferences = [];
        $secondaryReferences = [];

        foreach ($characters as $character) {
            foreach ($character['references'] as $index => $reference) {
                if ($index === 0) {
                    $primaryReferences[] = $reference['media_id'];
                } else {
                    $secondaryReferences[] = $reference['media_id'];
                }
            }
        }

        $sourceReferencePosition = $sourceMediaId !== null && $shot->transition_mode === TransitionMode::NEW_SHOT ? count($primaryReferences) : null;
        $availableReferences = $sourceReferencePosition === null ? 10 : 9;

        if (count($primaryReferences) > $availableReferences) {
            throw ValidationException::withMessages(['characters' => 'A continuity reference leaves room for at most nine character identities.']);
        }

        $referenceMediaIds = [...$primaryReferences, ...array_slice($secondaryReferences, 0, $availableReferences - count($primaryReferences))];
        $specification = json_encode($series->specification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $characterPrompt = json_encode(collect($characters)->map(fn(array $character): array => [
            'id' => $character['id'], 'character_id' => $character['character_id'], 'description' => $character['description'],
            'name' => $character['name'],
            'appearance' => $character['appearance'], 'voice' => $character['voice'],
        ])->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $setting = json_encode($scene->setting, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $camera = json_encode($shot->camera, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $dialogue = json_encode($shot->dialogue, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $initialState = json_encode($scene->initial_state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $sound = match (true) {
            $audioMode === SeriesAudioMode::SILENT => 'Completely silent video. No speech, narration, music, singing, sound effects or any other audio.',
            $hasDialogue => "Speak only these exact dialogue lines in the series language, using each character's pinned voice blueprint and delivery: $dialogue. Keep voices distinct and consistent. Do not narrate the production instructions.",
            default => 'No speech, narration, commentary, singing or intelligible vocals. Natural action sounds and suitable instrumental music only.',
        };
        $imagePrompt = <<<TEXT
Create the opening frame of this scripted shot using the supplied character references.
Series specification: $specification
Pinned character appearance and voice versions: $characterPrompt
Scene setting: $setting
Initial state: $initialState
Camera: $camera
Action to start, not its completed outcome: {$shot->action}
Use the series visual style, including animation when specified. Keep each referenced character recognisable. Show only characters required by the action. Output one portrait 9:16 frame, with no text, captions, logos or watermark.
TEXT;
        $videoPrompt = <<<TEXT
Produce this scripted {$duration}-second shot.
Series specification: $specification
Pinned character versions: $characterPrompt
Scene: $setting
Initial state: $initialState
Camera: $camera
Action: {$shot->action}
Transition: {$shot->transition_mode->value}. Begin directly from the supplied first frame. Preserve appearance, clothing, object positions and coherent motion. Do not resolve an unfinished scene unless the action requires it.
Sound: $sound
No extra dialogue, printed captions, logos or watermark. Do not replace the series style with an unrelated style.
TEXT;

        return [
            'schema_version' => 1, 'actor_id' => $actor->id, 'series_id' => $series->id,
            'episode_revision_id' => $revision->id, 'specification_version' => $series->specification_version,
            'continuity_version' => $series->continuity_version, 'context_hash' => $revision->context_hash,
            'characters' => $characters, 'transition_mode' => $shot->transition_mode->value,
            'audio_mode' => $audioMode->value,
            'reference_media_ids' => $referenceMediaIds, 'source_reference_position' => $sourceReferencePosition,
            'source_media_id' => $sourceMediaId, 'source_sha256' => $sourceChecksum,
            'source_prompt_id' => $sourcePromptId, 'source_assembly_id' => $sourceAssemblyId,
            'source_time_ms' => $shot->source_anchor['time_ms'] ?? null,
            'image_prompt' => $imagePrompt, 'video_prompt' => $videoPrompt,
            'video' => ['provider' => $provider->value, 'model' => $capabilities->model, 'duration' => $duration,
                'resolution' => $capabilities->defaultResolution, 'aspect_ratio' => $capabilities->defaultAspectRatio,
                'generate_audio' => $generateAudio, 'return_last_frame' => $capabilities->supportsReturnLastFrame],
        ];
    }
}
