<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Events\WorkspaceUpdated;
use App\Models\Episode;
use App\Repositories\EpisodeRepository;
use Illuminate\Validation\ValidationException;

class CompleteEpisodeScriptRequest
{
    public function __construct(private readonly EpisodeRepository $episodes) {}

    public function run(Episode $episode, string $requestKey, ?string $error = null): void
    {
        $updated = $this->episodes->completeScriptRequest($episode, $requestKey, $error);

        if ($error === null && $updated !== 1) {
            throw ValidationException::withMessages(['script' => 'This screenplay request is no longer active.']);
        }

        if ($updated > 0) {
            WorkspaceUpdated::forResource($episode);
        }
    }
}
