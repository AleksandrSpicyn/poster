<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Models\Shot;
use App\Models\User;
use App\Tasks\Shots\PrepareShotCharacters;
use App\Tasks\Shots\PrepareShotContinuation;
use App\Tasks\Shots\PrepareShotGenerationInput;
use App\Tasks\Shots\PrepareShotVideoSettings;

readonly class PrepareShotGenerationInputSubAction
{
    public function __construct(
        private PrepareShotVideoSettings $video,
        private PrepareShotCharacters $characters,
        private PrepareShotContinuation $continuation,
        private PrepareShotGenerationInput $snapshot,
    ) {}

    /** @return array<string, mixed> */
    public function run(Shot $shot, User $actor, ?int $sourcePromptId): array
    {
        $video = $this->video->run($shot);
        $characters = $this->characters->run($shot);
        $source = $this->continuation->run($shot, $sourcePromptId);

        return $this->snapshot->run($shot, $actor, $video, $characters, $source);
    }
}
