<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ReservationStatus;
use App\Models\GenerationOperation;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\PromptRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGenerationOperation
{
    public function __construct(
        private readonly GenerationReservationRepository $usage,
        private readonly GenerationOperationRepository $operations,
        private readonly PromptRepository $prompts,
        private readonly ProductionRunRepository $runs,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function run(GenerationOperation $operation, GenerationOperationStatus $status, array $attributes = [], ?GenerationOperationStatus $expectedStatus = null): ?GenerationOperation
    {
        return DB::transaction(function () use ($operation, $status, $attributes, $expectedStatus): ?GenerationOperation {
            if ($operation->production_run_id !== null) {
                $this->runs->findLockedOrFail($operation->production_run_id);
            }

            $prompt = $this->prompts->findLockedOrFail($operation->prompt_id);
            $operation = $this->operations->findLockedOrFail($operation->id);

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
            $this->operations->update($operation, [
                ...$attributes,
                'status' => $cancelled ? GenerationOperationStatus::CANCELLED : $status,
                'completed_at' => $completed ? now() : null,
                'next_check_at' => in_array($status, [
                    GenerationOperationStatus::PLANNED,
                    GenerationOperationStatus::SUBMITTED,
                    GenerationOperationStatus::RUNNING,
                ], true) ? now()->addMinute() : null,
            ]);

            if ($status === GenerationOperationStatus::FAILED && $operation->stage !== GenerationOperationStage::DOWNLOAD) {
                $this->usage->setStatus([$prompt->id], ReservationStatus::RELEASED);
            }

            if ($status !== GenerationOperationStatus::SUCCEEDED) {
                return null;
            }

            if ($operation->stage === GenerationOperationStage::DOWNLOAD) {
                $this->prompts->update($prompt, ['generated' => ! $cancelled]);
                $this->usage->setStatus([$prompt->id], $cancelled ? ReservationStatus::RELEASED : ReservationStatus::CONSUMED);

                return null;
            }

            if ($operation->stage === GenerationOperationStage::IMAGE && $cancelled) {
                $this->usage->setStatus([$prompt->id], ReservationStatus::RELEASED);

                return null;
            }

            $nextStage = $operation->stage === GenerationOperationStage::IMAGE ? GenerationOperationStage::VIDEO : GenerationOperationStage::DOWNLOAD;

            return $this->operations->createNextStage($prompt, $operation, $nextStage);
        });
    }
}
