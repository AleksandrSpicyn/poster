<?php

declare(strict_types=1);

namespace App\Classes\Story;

use App\Enums\CycleStructure;
use App\Enums\TransitionMode;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

class CyclePlanSchema
{
    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'objective' => $schema->string()->required(),
            'structure' => $schema->string()->enum(CycleStructure::values())->required(),
            'parts' => $schema->array()->min(1)->max(20)->items($schema->object([
                'number' => $schema->integer()->min(1)->max(20)->required(),
                'objective' => $schema->string()->required(),
                'expected_result' => $schema->string()->required(),
                'scene_key' => $schema->string()->required(),
                'transition_mode' => $schema->string()->enum(TransitionMode::values())->required(),
            ]))->required(),
        ];
    }
}
