<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Data\Shots\ShotContinuationData;
use App\Enums\AssemblyStatus;
use App\Enums\TransitionMode;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Shot;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\PromptRepository;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class PrepareShotContinuation
{
    public function __construct(private PromptRepository $prompts, private EpisodeAssemblyRepository $assemblies) {}

    public function run(Shot $shot, ?int $sourcePromptId): ShotContinuationData
    {
        $scene = $shot->scene ?? throw new RuntimeException('Shot scene is missing.');
        $revision = $scene->episodeRevision ?? throw new RuntimeException('Shot screenplay is missing.');
        $episode = $revision->episode ?? throw new RuntimeException('Shot episode is missing.');
        $series = $episode->series ?? throw new RuntimeException('Shot series is missing.');
        $sourceMediaId = null;
        $sourceChecksum = null;
        $sourcePromptId ??= $shot->source_prompt_id;
        $sourceAssemblyId = $shot->source_assembly_id ?? $revision->source_assembly_id;

        if ($shot->transition_mode !== TransitionMode::NEW_SCENE) {
            $media = null;

            if ($sourcePromptId !== null) {
                $source = $this->prompts->forContinuation($sourcePromptId);
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
                $source = $this->assemblies->forContinuation($sourceAssemblyId);

                if ($source->status !== AssemblyStatus::ACCEPTED || $source->id !== $revision->source_assembly_id
                    || $source->episodeRevision?->episode?->series_id !== $series->id) {
                    throw ValidationException::withMessages(['source_assembly_id' => 'Select the accepted predecessor assembly pinned by this screenplay.']);
                }

                if ($shot->transition_mode === TransitionMode::CONTINUE_SCENE && ($source->continuity_snapshot['scene_closed'] ?? null) === true) {
                    throw ValidationException::withMessages(['source_assembly_id' => 'The accepted preceding scene is closed. Start a new scene.']);
                }

                $media = $source->getFirstMedia(EpisodeAssembly::VIDEO);
            }

            if ($shot->transition_mode === TransitionMode::CONTINUE_SCENE && ! $media instanceof Media) {
                throw ValidationException::withMessages(['source_prompt_id' => 'A continued scene requires an earlier ready take or an accepted predecessor video.']);
            }

            if ($media instanceof Media) {
                $sourceMediaId = $media->id;
                $sourceChecksum = hash_file('sha256', $media->getPath());

                if ($sourceChecksum === false) {
                    throw ValidationException::withMessages(['source' => 'The continuation source file is missing.']);
                }
            }
        }

        return new ShotContinuationData($sourceMediaId, $sourceChecksum, $sourcePromptId, $sourceAssemblyId);
    }
}
