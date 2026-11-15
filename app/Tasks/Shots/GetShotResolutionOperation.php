<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\VideoProvider;
use App\Models\GenerationOperation;
use App\Models\User;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GetShotResolutionOperation
{
    public function run(GenerationOperation $operation, string $externalId, User $actor): GenerationOperation
    {
        Gate::forUser($actor->fresh())->authorize('manageSeries');
        $operation = GenerationOperation::query()->with('prompt.shot.scene.episodeRevision.episode.series')->findOrFail($operation->id);
        $series = $operation->prompt?->shot?->scene?->episodeRevision?->episode?->series;

        if ($series === null || $series->archived_at !== null
            || $operation->stage !== GenerationOperationStage::VIDEO
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,199}\z/', $externalId) !== 1
            || ($operation->external_id !== null && $operation->external_id !== $externalId)) {
            throw ValidationException::withMessages(['external_id' => 'Select a valid remote video receipt for this generation.']);
        }

        if ($operation->status !== GenerationOperationStatus::SUBMISSION_UNKNOWN
            && ($operation->external_id !== $externalId || ! in_array($operation->status, [GenerationOperationStatus::SUBMITTED, GenerationOperationStatus::RUNNING, GenerationOperationStatus::SUCCEEDED, GenerationOperationStatus::FAILED, GenerationOperationStatus::CANCELLED], true))) {
            throw ValidationException::withMessages(['operation' => 'Only an unknown submission can be resolved with a remote receipt.']);
        }

        $operation->provider = VideoProvider::from(Payload::string(data_get($operation->request_snapshot, 'video.provider'), 'provider'));
        $operation->model = Payload::string(data_get($operation->request_snapshot, 'video.model'), 'model');

        return $operation;
    }
}
