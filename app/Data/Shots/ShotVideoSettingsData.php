<?php

declare(strict_types=1);

namespace App\Data\Shots;

use App\Data\VideoCapabilitiesData;
use App\Enums\SeriesAudioMode;
use App\Enums\VideoProvider;
use Spatie\LaravelData\Data;

class ShotVideoSettingsData extends Data
{
    public function __construct(
        public readonly VideoProvider $provider,
        public readonly VideoCapabilitiesData $capabilities,
        public readonly int $duration,
        public readonly SeriesAudioMode $audioMode,
        public readonly bool $generateAudio,
        public readonly string $resolution,
    ) {}

    /** @return array{provider: string, model: string, duration: int, resolution: string, aspect_ratio: string, generate_audio: bool, return_last_frame: bool} */
    public function videoSnapshot(): array
    {
        return [
            'provider' => $this->provider->value,
            'model' => $this->capabilities->model,
            'duration' => $this->duration,
            'resolution' => $this->resolution,
            'aspect_ratio' => $this->capabilities->defaultAspectRatio,
            'generate_audio' => $this->generateAudio,
            'return_last_frame' => $this->capabilities->supportsReturnLastFrame,
        ];
    }
}
