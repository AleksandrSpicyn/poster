<?php

declare(strict_types=1);

namespace App\Data\Series;

use App\Enums\ContentAudience;
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
        public int $episode_duration_seconds = 30,
        #[Max(20000)]
        public string $narrative_rules = '',
        public SeriesAudioMode $audio_mode = SeriesAudioMode::NATIVE,
    ) {}
}
