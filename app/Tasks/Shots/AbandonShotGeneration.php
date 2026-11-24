<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Data\Shots\AbandonShotGenerationData;
use App\Enums\GenerationOperationStatus;
use App\Enums\ReservationStatus;
use App\Models\GenerationOperation;
use App\Models\User;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AbandonShotGeneration
{
    public function __construct(
        private readonly GenerationReservationRepository $usage,
        private readonly PromptRepository $prompts,
        private readonly GenerationOperationRepository $operations,
        private readonly UserRepository $users,
    ) {}

    public function run(GenerationOperation $operation, AbandonShotGenerationData $data, User $actor): GenerationOperation
    {
        $actor = $this->users->fresh($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        if (! $data->accept_unknown_outcome || trim($data->note) === '' || mb_strlen($data->note) > 1000) {
            throw ValidationException::withMessages(['operation' => 'A note and explicit acceptance of the unknown external outcome are required.']);
        }

        return DB::transaction(function () use ($operation, $data, $actor): GenerationOperation {
            $prompt = $this->prompts->findLockedOrFail($operation->prompt_id);
            $operation = $this->operations->lockForPromptOperation($prompt, $operation->id);

            if ($prompt->shot_id === null) {
                throw ValidationException::withMessages(['operation' => 'Select a generation belonging to a serial shot.']);
            }

            if ($operation->status === GenerationOperationStatus::ABANDONED) {
                return $operation;
            }

            if ($operation->status !== GenerationOperationStatus::SUBMISSION_UNKNOWN) {
                throw ValidationException::withMessages(['operation' => 'Only a submission with an unknown external outcome can be abandoned.']);
            }

            $this->operations->update($operation, [
                'status' => GenerationOperationStatus::ABANDONED,
                'completed_at' => now(),
                'next_check_at' => null,
                'result_snapshot' => [...($operation->result_snapshot ?? []),
                    'resolved_by' => $actor->id,
                    'resolution_note' => trim($data->note),
                    'resolved_at' => now()->toISOString(),
                    'accept_unknown_outcome' => true,
                ],
            ]);
            $this->usage->setStatus([$prompt->id], ReservationStatus::RELEASED);

            return $operation;
        });
    }
}
