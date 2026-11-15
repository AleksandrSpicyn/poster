<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RetryShotDownload
{
    public function run(Prompt $prompt, User $actor): GenerationOperation
    {
        Gate::forUser($actor->fresh())->authorize('manageSeries');

        return DB::transaction(function () use ($prompt, $actor): GenerationOperation {
            $prompt = Prompt::query()->lockForUpdate()->findOrFail($prompt->id);
            $operation = $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->lockForUpdate()->firstOrFail();

            if ($prompt->shot_id === null || ! is_string($operation->result_snapshot['video_url'] ?? null)) {
                throw ValidationException::withMessages(['download' => 'Select an existing video result for a verified user.']);
            }

            if (in_array($operation->status, [GenerationOperationStatus::PLANNED, GenerationOperationStatus::SUCCEEDED], true)) {
                return $operation;
            }

            if (! in_array($operation->status, [GenerationOperationStatus::FAILED, GenerationOperationStatus::CANCELLED], true)) {
                throw ValidationException::withMessages(['download' => 'Wait for the current download before retrying.']);
            }

            $operation->update(['status' => GenerationOperationStatus::PLANNED, 'next_check_at' => now(),
                'cancel_requested_at' => null, 'error_code' => null, 'completed_at' => null,
                'result_snapshot' => [...($operation->result_snapshot ?? []), 'retry_limit' => $operation->check_count + 3,
                    'retry_requested_by' => $actor->id, 'retry_requested_at' => now()->toISOString()]]);

            return $operation;
        });
    }
}
