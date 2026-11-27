<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use App\Support\DistinctModelIdRule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class ShotDirectionData extends Data
{
    /**
     * @param list<int>|null $visible_character_ids
     * @param list<int> $reference_ids
     * @param list<ShotCueData> $cues
     */
    public function __construct(
        #[Max(10)] public ?array $visible_character_ids = null,
        #[Max(10)] public array $reference_ids = [],
        #[DataCollectionOf(ShotCueData::class), Max(20)] public array $cues = [],
        public bool $review_frame = false,
        #[Max(3000)] public ?string $notes = null,
    ) {}

    /** @return array<string, DistinctModelIdRule> */
    public static function rules(): array
    {
        return ['visible_character_ids.*' => new DistinctModelIdRule(), 'reference_ids.*' => new DistinctModelIdRule()];
    }
}
