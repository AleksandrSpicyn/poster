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
    ) {}
}
