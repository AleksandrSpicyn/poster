<?php

declare(strict_types=1);

namespace App\Classes\Story;

use App\Enums\TransitionMode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

class EpisodeScriptSchema
{
    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $dialogue = $schema->object([
            'character_id' => $schema->integer()->min(1)->required(),
            'text' => $schema->string()->max(2000)->required(),
        ]);
        $shot = $schema->object([
            'duration_ms' => $schema->integer()->min(4000)->max(30000)->required(),
            'action' => $schema->string()->required(),
            'camera' => $schema->string()->required(),
            'transition_mode' => $schema->string()->enum(TransitionMode::values())->required(),
            'dialogue' => $schema->array()->items($dialogue)->required(),
        ]);
        $scene = $schema->object([
            'title' => $schema->string()->required(),
            'location' => $schema->string()->required(),
            'time' => $schema->string()->required(),
            'objective' => $schema->string()->required(),
            'initial_state' => $schema->string()->required(),
            'expected_final_state' => $schema->string()->required(),
            'is_closed' => $schema->boolean()->required(),
            'shots' => $schema->array()->items($shot)->min(1)->max(30)->required(),
        ]);

        return [
            'public_caption' => $schema->string()->max(1000)->required(),
            'title' => $schema->string()->required(),
            'objective' => $schema->string()->required(),
            'character_revision_ids' => $schema->array()->items($schema->integer())->min(1)->max(10)->required(),
            'scenes' => $schema->array()->items($scene)->min(1)->max(20)->required(),
        ];
    }
}
