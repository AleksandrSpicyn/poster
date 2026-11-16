<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\ScriptGenerationStatus;
use App\Models\Episode;
use App\Models\User;
use App\Repositories\EpisodeRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimEpisodeScript
{
    public function __construct(private readonly EpisodeRepository $episodes, private readonly UserRepository $users) {}

    public function run(Episode $episode, string $requestKey): ?Episode
    {
        return DB::transaction(function () use ($episode, $requestKey): ?Episode {
            $context = $this->episodes->lockScriptClaim($episode);
            $project = $context->project;
            $series = $context->series;
            $episode = $context->episode;

            if ($episode->script_request_key !== $requestKey || $episode->script_generation_status !== ScriptGenerationStatus::QUEUED) {
                return null;
            }

            $payload = $episode->script_request_payload;
            $actor = $episode->script_requested_by === null ? null : $this->users->find($episode->script_requested_by);
            $actorAllowed = $actor instanceof User && Gate::forUser($actor)->allows('manageSeries');

            if (! $project->is_active || ! $actorAllowed || $payload === null || $payload['expected_version'] !== $episode->lock_version
                || $series->archived_at !== null || ($payload['context']['continuity_version'] ?? null) !== $series->continuity_version
                || ($payload['context']['specification_version'] ?? null) !== $series->specification_version) {
                $this->episodes->update($episode, ['script_generation_status' => ScriptGenerationStatus::FAILED, 'script_generation_error' => 'The episode context or generation permission changed before execution.']);

                return null;
            }

            $this->episodes->update($episode, ['script_generation_status' => ScriptGenerationStatus::RUNNING, 'script_started_at' => now()]);

            return $episode;
        });
    }
}
