<?php

declare(strict_types=1);

namespace App\Classes\Story;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

class EpisodeOutcomeSchema
{
    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'usable' => $schema->boolean()->required(),
            'confirmed_events' => $schema->string()->max(12000)->required(),
            'character_state' => $schema->string()->max(8000)->required(),
            'open_threads' => $schema->string()->max(8000)->required(),
            'scene_closed' => $schema->boolean()->required(),
            'review_notes' => $schema->string()->max(2000)->required(),
        ];
    }
}
