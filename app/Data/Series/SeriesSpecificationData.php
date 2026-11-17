<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Enums\ContentAudience;
use App\Enums\CycleSceneMode;
use App\Enums\CycleStructure;
use App\Enums\SeriesAudioMode;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SeriesSpecificationData extends Data
{
    public function __construct(
        #[Required, Max(20000)]
        public string $premise,
        #[Max(2000)]
        public string $visual_style = 'cinematic',
        #[Max(35)]
        public string $language = 'en',
        public ContentAudience $audience = ContentAudience::GENERAL,
        #[Min(4), Max(300)]
        public int $episode_duration_seconds = 15,
        #[Max(20000)]
        public ?string $narrative_rules = '',
        public SeriesAudioMode $audio_mode = SeriesAudioMode::NATIVE,
        #[Max(20000)]
        public ?string $story_outline = null,
        public CycleStructure $cycle_structure = CycleStructure::CONTINUOUS_STORY,
        public CycleSceneMode $scene_mode = CycleSceneMode::SCRIPTED,
        #[Min(4), Max(30)] public int $shot_duration_seconds = 15,
    ) {}
}
