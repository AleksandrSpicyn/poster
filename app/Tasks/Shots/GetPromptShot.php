<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Models\Prompt;
use App\Models\Shot;
use App\Repositories\ShotRepository;

readonly class GetPromptShot
{
    public function __construct(private ShotRepository $shots) {}

    public function run(Prompt $prompt): ?Shot
    {
        return $this->shots->forPrompt($prompt);
    }
}
