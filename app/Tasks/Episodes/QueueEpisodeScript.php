<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Enums\ScriptGenerationStatus;
use App\Models\Episode;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueEpisodeScript
{
    /** @param array<string, mixed> $context */
    public function run(Episode $episode, GenerateEpisodeScriptData $data, array $context, int $actorId): bool
    {
        return DB::transaction(function () use ($episode, $data, $context, $actorId): bool {
            $series = $episode->series()->firstOrFail();
            $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);

            if (! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before generating a screenplay.']);
            }

            $episode = Episode::query()->lockForUpdate()->findOrFail($episode->id);

            if ($episode->script_request_key === $data->request_key) {
                if (($episode->script_request_payload['brief'] ?? null) !== $data->brief
                    || ($episode->script_request_payload['expected_version'] ?? null) !== $data->lock_version) {
                    throw ValidationException::withMessages(['request_key' => 'This request key belongs to different input.']);
                }

                return false;
            }

            if ($episode->lock_version !== $data->lock_version
                || in_array($episode->script_generation_status, [ScriptGenerationStatus::QUEUED, ScriptGenerationStatus::RUNNING], true)) {
                throw ValidationException::withMessages(['lock_version' => 'The episode changed or a screenplay request is already active.']);
            }

            $episode->update([
                'script_request_key' => $data->request_key,
                'script_requested_by' => $actorId,
                'script_generation_status' => ScriptGenerationStatus::QUEUED,
                'script_request_payload' => ['brief' => $data->brief, 'context' => $context, 'expected_version' => $data->lock_version],
                'script_generation_error' => null,
                'script_started_at' => null,
            ]);

            return true;
        });
    }
}
