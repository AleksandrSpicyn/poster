<?php

declare(strict_types=1);

namespace App\Classes\Generators\PromptGenerator\Codex;

use App\Classes\Generators\PromptGenerator\PromptGenerator;
use App\Support\Integrations\Payload;
use Illuminate\Container\Attributes\Config;

use function Laravel\Ai\agent;

readonly class Codex implements PromptGenerator
{
    public function __construct(#[Config('services.codex.timeout')] private int $timeout) {}

    public function generate(string $prompt): string
    {
        $response = agent()->prompt($prompt, provider: 'codex', timeout: $this->timeout);

        return trim(Payload::string($response->text, 'text'));
    }
}
