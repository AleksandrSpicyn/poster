<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGenerationOperation
{
    /** @param array<string, mixed> $attributes */
    public function run(GenerationOperation $operation, GenerationOperationStatus $status, array $attributes = [], ?GenerationOperationStatus $expectedStatus = null): ?GenerationOperation
    {
        return DB::transaction(function () use ($operation, $status, $attributes, $expectedStatus): ?GenerationOperation {
            $prompt = Prompt::query()->lockForUpdate()->findOrFail($operation->prompt_id);
            $operation = GenerationOperation::query()->lockForUpdate()->findOrFail($operation->id);

            if ($operation->status === GenerationOperationStatus::ABANDONED) {
                return null;
            }

            if ($expectedStatus instanceof GenerationOperationStatus && $operation->status !== $expectedStatus) {
                if ($operation->external_id === ($attributes['external_id'] ?? null)) {
                    return null;
                }

                throw ValidationException::withMessages(['operation' => 'The generation changed while its remote receipt was being checked.']);
            }

            if ($operation->status === GenerationOperationStatus::SUCCEEDED || $operation->status === GenerationOperationStatus::CANCELLED) {
                return null;
            }

            $completed = in_array($status, [GenerationOperationStatus::SUCCEEDED, GenerationOperationStatus::FAILED], true);
            $cancelled = $completed && $operation->cancel_requested_at !== null;
            $operation->update([...$attributes, 'status' => $cancelled ? GenerationOperationStatus::CANCELLED : $status,
                'completed_at' => $completed ? now() : null,
                'next_check_at' => in_array($status, [GenerationOperationStatus::PLANNED, GenerationOperationStatus::SUBMITTED, GenerationOperationStatus::RUNNING], true) ? now()->addMinute() : null]);

            if ($status !== GenerationOperationStatus::SUCCEEDED) {
                return null;
            }

            if ($operation->stage === GenerationOperationStage::DOWNLOAD) {
                $prompt->update(['generated' => ! $cancelled]);

                return null;
            }

            if ($operation->stage === GenerationOperationStage::IMAGE && $cancelled) {
                return null;
            }

            $nextStage = $operation->stage === GenerationOperationStage::IMAGE ? GenerationOperationStage::VIDEO : GenerationOperationStage::DOWNLOAD;

            return $prompt->generationOperations()->firstOrCreate(['stage' => $nextStage], [
                'status' => GenerationOperationStatus::PLANNED,
                'request_key' => $operation->request_key,
                'idempotency_key' => hash('sha256', $operation->request_key . ':' . $nextStage->value),
                'provider' => $operation->provider,
                'model' => $operation->model,
                'request_snapshot' => $operation->request_snapshot,
                'result_snapshot' => $operation->result_snapshot,
                'cancel_requested_at' => $operation->cancel_requested_at,
                'next_check_at' => now(),
            ]);
        });
    }
}
