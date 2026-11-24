<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class EpisodeScriptData extends Data
{
    /**
     * @param list<int> $character_revision_ids
     * @param list<SceneData> $scenes
     */
    public function __construct(
        #[Min(1), Max(255)] public string $title,
        #[Min(1), Max(5000)] public string $objective,
        #[Min(1), Max(10)] public array $character_revision_ids,
        #[DataCollectionOf(SceneData::class), Min(1), Max(20)] public array $scenes,
        #[Max(1000)] public ?string $public_caption = null,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['character_revision_ids.*' => [
            'integer',
            'min:1',
            'distinct',
        ]];
    }
}
