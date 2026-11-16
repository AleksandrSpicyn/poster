<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Generators\VideoGenerator\VideoGeneratorFactory;
use App\Data\Shots\ShotVideoSettingsData;
use App\Data\VideoGenerationRequestData;
use App\Enums\SeriesAudioMode;
use App\Models\Shot;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

readonly class PrepareShotVideoSettings
{
    public function __construct(private VideoGeneratorFactory $generators) {}

    public function run(Shot $shot): ShotVideoSettingsData
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

        return new ShotVideoSettingsData($provider, $capabilities, $duration, $audioMode, $generateAudio);
    }
}
