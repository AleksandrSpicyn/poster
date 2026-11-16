<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\Prompt;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\PromptRepository;
use Illuminate\Support\Facades\DB;

class CancelShotGeneration
{
    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly GenerationOperationRepository $operations,
    ) {}

    public function run(Prompt $prompt): void
    {
        DB::transaction(function () use ($prompt): void {
            $prompt = $this->prompts->findLockedOrFail($prompt->id);

            foreach ($this->operations->lockForPrompt($prompt) as $operation) {
                if (! $operation->status->isActive()) {
                    continue;
                }

                $this->operations->update($operation, ['cancel_requested_at' => now(),
                    'status' => $operation->status === GenerationOperationStatus::PLANNED && $operation->stage !== GenerationOperationStage::DOWNLOAD
                        ? GenerationOperationStatus::CANCELLED : $operation->status]);
            }
        });
    }
}
