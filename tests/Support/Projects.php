<?php

declare(strict_types=1);

namespace Tests\Support;

/** @return array{name: string, prompt: string} */
function projectPayload(): array
{
    return ['name' => 'Wildlife stories', 'prompt' => 'Playful short stories about animals discovering new places.'];
}

/** @return array{name: string, prompt: string, content_mode: string, series_specification: array<string, mixed>, automation_enabled: bool, episode_limit: int} */
function seriesProjectPayload(): array
{
    return [
        ...projectPayload(),
        'content_mode' => 'series',
        'series_specification' => [...serialSeriesPayload()['specification'], 'episode_duration_seconds' => 10,
            'story_outline' => 'Five small adventures with a clear ending in the last episode.'],
        'automation_enabled' => true,
        'episode_limit' => 5,
    ];
}

/** @return array<string, array{array<string, mixed>, string}> */
function invalidProjectInputs(): array
{
    return [
        'пустое имя' => [['name' => ''], 'name'],
        'null имени' => [['name' => null], 'name'],
        'имя массивом' => [['name' => ['invalid']], 'name'],
        'длинное имя' => [['name' => str_repeat('a', 256)], 'name'],
        'пустой промпт' => [['prompt' => ''], 'prompt'],
        'null промпта' => [['prompt' => null], 'prompt'],
        'промпт массивом' => [['prompt' => ['invalid']], 'prompt'],
        'длинный промпт' => [['prompt' => str_repeat('a', 10001)], 'prompt'],
    ];
}
