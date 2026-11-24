<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStatus;
use App\Enums\ReservationStatus;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\User;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RetryShotDownload
{
    public function __construct(
        private readonly GenerationReservationRepository $usage,
        private readonly PromptRepository $prompts,
        private readonly GenerationOperationRepository $operations,
        private readonly UserRepository $users,
    ) {}

    public function run(Prompt $prompt, User $actor): GenerationOperation
    {
        Gate::forUser($this->users->fresh($actor))->authorize('manageSeries');

        return DB::transaction(function () use ($prompt, $actor): GenerationOperation {
            $prompt = $this->prompts->findLockedOrFail($prompt->id);
            $operation = $this->operations->lockDownloadForPrompt($prompt);

            if ($prompt->shot_id === null || ! is_string($operation->result_snapshot['video_url'] ?? null)) {
                throw ValidationException::withMessages(['download' => 'Select an existing video result for a verified user.']);
            }

            if (in_array($operation->status, [GenerationOperationStatus::PLANNED, GenerationOperationStatus::SUCCEEDED], true)) {
                return $operation;
            }

            if (! in_array($operation->status, [GenerationOperationStatus::FAILED, GenerationOperationStatus::CANCELLED], true)) {
                throw ValidationException::withMessages(['download' => 'Wait for the current download before retrying.']);
            }

            $this->operations->update($operation, [
                'status' => GenerationOperationStatus::PLANNED,
                'next_check_at' => now(),
                'cancel_requested_at' => null,
                'error_code' => null,
                'completed_at' => null,
                'result_snapshot' => [
                    ...($operation->result_snapshot ?? []),
                    'retry_limit' => $operation->check_count + 3,
                    'retry_requested_by' => $actor->id,
                    'retry_requested_at' => now()->toISOString(),
                ],
            ]);

            $this->usage->transitionTakeReservations([$prompt->id], ReservationStatus::HELD);

            return $operation;
        });
    }
}
