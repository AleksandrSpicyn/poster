<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Story\Snapshot;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimGenerationOperation
{
    public function run(int $operationId, bool $polling = false): ?GenerationOperation
    {
        return DB::transaction(function () use ($operationId, $polling): ?GenerationOperation {
            $operation = GenerationOperation::query()->findOrFail($operationId);
            $productionRun = ! $polling && $operation->stage !== GenerationOperationStage::DOWNLOAD && $operation->production_run_id !== null
                ? ProductionRun::query()->lockForUpdate()->find($operation->production_run_id) : null;
            $prompt = Prompt::query()->lockForUpdate()->findOrFail($operation->prompt_id);
            $operation = GenerationOperation::query()->lockForUpdate()->findOrFail($operationId);

            if ($polling) {
                if (! in_array($operation->status, [GenerationOperationStatus::SUBMITTED, GenerationOperationStatus::RUNNING], true)
                    || ($operation->next_check_at !== null && $operation->next_check_at->isFuture())) {
                    return null;
                }

                $operation->update(['status' => GenerationOperationStatus::RUNNING, 'check_count' => $operation->check_count + 1, 'next_check_at' => now()->addMinute()]);

                return $operation;
            }

            if ($operation->status !== GenerationOperationStatus::PLANNED
                || ($operation->next_check_at !== null && $operation->next_check_at->isFuture())) {
                return null;
            }

            if ($operation->stage !== GenerationOperationStage::DOWNLOAD) {
                if ($operation->cancel_requested_at !== null || ($operation->production_run_id !== null
                    && ($productionRun === null || ! $productionRun->status->isActive()
                        || $productionRun->series?->automation_enabled !== true || $productionRun->series->archived_at !== null))) {
                    $operation->update(['status' => GenerationOperationStatus::CANCELLED, 'cancel_requested_at' => now(),
                        'completed_at' => now(), 'next_check_at' => null]);

                    return null;
                }

                $actorId = $operation->request_snapshot['actor_id'] ?? null;
                $actor = is_int($actorId) ? User::query()->find($actorId) : null;

                if ($actor === null || ! Gate::forUser($actor)->allows('manageSeries')
                    || $prompt->input_hash !== Snapshot::hash($operation->request_snapshot)) {
                    $operation->update(['status' => GenerationOperationStatus::FAILED,
                        'error_code' => 'invalid_actor_or_snapshot', 'completed_at' => now(), 'next_check_at' => null]);

                    return null;
                }
            }

            $operation->update(['status' => GenerationOperationStatus::SUBMITTING, 'started_at' => now(), 'next_check_at' => null,
                'check_count' => $operation->stage === GenerationOperationStage::DOWNLOAD ? $operation->check_count + 1 : $operation->check_count]);

            return $operation->load('prompt.shot');
        });
    }
}
