<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\ReservationStatus;
use App\Models\Prompt;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use Illuminate\Support\Facades\DB;

class CancelShotGeneration
{
    public function __construct(
        private readonly GenerationReservationRepository $usage,
        private readonly PromptRepository $prompts,
        private readonly GenerationOperationRepository $operations,
    ) {}

    public function run(Prompt $prompt): void
    {
        DB::transaction(function () use ($prompt): void {
            $prompt = $this->prompts->findLockedOrFail($prompt->id);

            $stopped = $this->operations->cancelActive($this->operations->lockForPrompt($prompt));
            $this->usage->setStatus($stopped, ReservationStatus::RELEASED);
        });
    }
}
