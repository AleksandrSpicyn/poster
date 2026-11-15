<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\ScriptGenerationStatus;
use App\Models\Episode;
use Illuminate\Validation\ValidationException;

class CompleteEpisodeScriptRequest
{
    public function run(Episode $episode, string $requestKey, ?string $error = null): void
    {
        $updated = Episode::query()->whereKey($episode->id)->where('script_request_key', $requestKey)
            ->where('script_generation_status', ScriptGenerationStatus::RUNNING)
            ->update([
                'script_generation_status' => $error === null ? ScriptGenerationStatus::COMPLETED : ScriptGenerationStatus::FAILED,
                'script_generation_error' => $error,
            ]);

        if ($error === null && $updated !== 1) {
            throw ValidationException::withMessages(['script' => 'This screenplay request is no longer active.']);
        }
    }
}
