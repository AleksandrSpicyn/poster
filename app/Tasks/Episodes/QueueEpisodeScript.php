<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\GenerateEpisodeScriptData;
use App\Enums\ScriptGenerationStatus;
use App\Models\Episode;
use App\Repositories\EpisodeRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueEpisodeScript
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly ProjectRepository $projects,
        private readonly SeriesRepository $series,
    ) {}

    /** @param array<string, mixed> $context */
    public function run(Episode $episode, GenerateEpisodeScriptData $data, array $context, int $actorId): bool
    {
        return DB::transaction(function () use ($episode, $data, $context, $actorId): bool {
            $series = $this->series->forEpisode($episode);
            $project = $this->projects->findLockedOrFail($series->project_id);

            if (! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before generating a screenplay.']);
            }

            $episode = $this->episodes->findLockedOrFail($episode->id);

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

            $this->episodes->update($episode, [
                'script_request_key' => $data->request_key,
                'script_requested_by' => $actorId,
                'script_generation_status' => ScriptGenerationStatus::QUEUED,
                'script_request_payload' => [
                    'brief' => $data->brief,
                    'context' => $context,
                    'expected_version' => $data->lock_version,
                ],
                'script_generation_error' => null,
                'script_started_at' => null,
            ]);

            return true;
        });
    }
}
