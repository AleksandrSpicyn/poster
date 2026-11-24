<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Story\Snapshot;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ReservationStatus;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\User;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClaimGenerationOperation
{
    public function __construct(private readonly GenerationOperationRepository $operations, private readonly UserRepository $users, private readonly GenerationReservationRepository $usage) {}

    public function run(int $operationId, bool $polling = false): ?GenerationOperation
    {
        return DB::transaction(function () use ($operationId, $polling): ?GenerationOperation {
            $context = $this->operations->lockClaimContext($operationId, $polling);
            $operation = $context->operation;
            $project = $context->project;
            $productionRun = $context->productionRun;
            $prompt = $context->prompt;

            if ($polling) {
                if (! in_array($operation->status, [GenerationOperationStatus::SUBMITTED, GenerationOperationStatus::RUNNING], true)
                    || ($operation->next_check_at !== null && $operation->next_check_at->isFuture())) {
                    return null;
                }

                $this->operations->update($operation, [
                    'status' => GenerationOperationStatus::RUNNING,
                    'check_count' => $operation->check_count + 1,
                    'next_check_at' => now()->addMinute(),
                ]);

                return $operation;
            }

            if ($operation->status !== GenerationOperationStatus::PLANNED
                || ($operation->next_check_at !== null && $operation->next_check_at->isFuture())) {
                return null;
            }

            if ($operation->stage !== GenerationOperationStage::DOWNLOAD) {
                if ($context->series?->cycle_paused_at !== null || $project?->is_active !== true || $operation->cancel_requested_at !== null || ($operation->production_run_id !== null
                    && (! $productionRun instanceof ProductionRun || ! $productionRun->status->isActive()
                        || $productionRun->series === null || (! $productionRun->series->automation_enabled && ! $productionRun->wasStartedManually()) || $productionRun->series->archived_at !== null))) {
                    $this->operations->update($operation, [
                        'status' => GenerationOperationStatus::CANCELLED,
                        'cancel_requested_at' => now(),
                        'completed_at' => now(),
                        'next_check_at' => null,
                    ]);

                    $this->usage->setStatus([$prompt->id], ReservationStatus::RELEASED);

                    return null;
                }

                $actorId = $operation->request_snapshot['actor_id'] ?? null;
                $actor = is_int($actorId) ? $this->users->find($actorId) : null;

                if (! $actor instanceof User || $actor->id !== $project->owner_id || ! Gate::forUser($actor)->allows('manageSeries')
                    || $prompt->input_hash !== Snapshot::hash($operation->request_snapshot)) {
                    $this->operations->update($operation, [
                        'status' => GenerationOperationStatus::FAILED,
                        'error_code' => 'invalid_actor_or_snapshot',
                        'completed_at' => now(),
                        'next_check_at' => null,
                    ]);

                    $this->usage->setStatus([$prompt->id], ReservationStatus::RELEASED);

                    return null;
                }
            }

            $this->operations->update($operation, [
                'status' => GenerationOperationStatus::SUBMITTING,
                'started_at' => now(),
                'next_check_at' => null,
                'check_count' => $operation->stage === GenerationOperationStage::DOWNLOAD ? $operation->check_count + 1 : $operation->check_count,
            ]);

            return $this->operations->withPromptShot($operation);
        });
    }
}
