<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\ScriptGenerationStatus;
use App\Models\Episode;

class DeferEpisodeScriptRequest
{
    public function run(Episode $episode, string $requestKey): void
    {
        Episode::query()->whereKey($episode->id)->where('script_request_key', $requestKey)
            ->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->update(['script_generation_status' => ScriptGenerationStatus::QUEUED,
                'script_started_at' => null, 'script_generation_error' => null]);
    }
}
