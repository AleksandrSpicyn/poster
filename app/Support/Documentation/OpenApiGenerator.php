<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use App\Enums\ChannelType;
use App\Enums\FilterValue;
use App\Enums\SortDirection;
use Illuminate\Container\Attributes\Config;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator as ScribeGenerator;

/**
 * Адаптирует Scribe OpenAPI к session-cookie аутентификации кабинета
 * и реальным ограничениям query DTO; документ используется также read-only MCP.
 */
class OpenApiGenerator extends ScribeGenerator
{
    public function __construct(DocumentationConfig $config, #[Config('session.cookie')] private readonly string $sessionCookie)
    {
        parent::__construct($config);
    }

    /**
     * @param array<string, mixed> $root
     * @param array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}> $groupedEndpoints
     * @return array<string, mixed>
     */
    public function root(array $root, array $groupedEndpoints): array
    {
        /** @var array<string, mixed> $document */
        $document = array_replace_recursive($root, [
            'components' => ['securitySchemes' => ['default' => [
                'type' => 'apiKey', 'in' => 'cookie', 'name' => $this->sessionCookie,
            ]]],
        ]);

        return $document;
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
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'perPage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10],
                'sort' => ['type' => 'string', 'enum' => SortDirection::values(), 'default' => SortDirection::DESC->value],
                'type' => ['type' => 'string', 'enum' => [FilterValue::ALL->value, ...ChannelType::values()]],
                default => [],
            });
        }

        $pathItem['operationId'] = strtolower($endpoint->httpMethods[0]) . '_'
            . str_replace(['/', '{', '}', '-'], ['_', '', '', '_'], trim($endpoint->uri, '/') ?: 'root');
        $pathItem['parameters'] = $parameters;

        return $pathItem;
    }
}
