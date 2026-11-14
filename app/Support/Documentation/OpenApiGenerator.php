<?php

namespace App\Support\Documentation;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator as ScribeGenerator;

class OpenApiGenerator extends ScribeGenerator
{
    /**
     * @param array<string, mixed> $root
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @return array<string, mixed>
     */
    public function root(array $root, array $groupedEndpoints): array
    {
        return array_replace_recursive($root, [
            'components' => ['securitySchemes' => ['default' => [
                'type' => 'apiKey', 'in' => 'cookie', 'name' => config()->string('session.cookie'),
            ]]],
        ]);
    }

    /**
     * @param array<string, mixed> $pathItem
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        /** @var list<array{name: string, in: string, schema: array<string, mixed>}> $parameters */
        $parameters = $pathItem['parameters'] ?? [];

        foreach ($parameters as &$parameter) {
            if ($parameter['in'] !== 'query') {
                continue;
            }

            $parameter['schema'] = array_replace($parameter['schema'], match ($parameter['name']) {
                'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10],
                'sort' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
                'type' => ['type' => 'string', 'enum' => ['All', 'youtube', 'tiktok', 'instagram', 'vk']],
                default => [],
            });
        }

        $pathItem['parameters'] = $parameters;

        return $pathItem;
    }
}
