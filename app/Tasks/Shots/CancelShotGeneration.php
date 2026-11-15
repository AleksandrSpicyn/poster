<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\Prompt;
use Illuminate\Support\Facades\DB;

class CancelShotGeneration
{
    public function run(Prompt $prompt): void
    {
        DB::transaction(function () use ($prompt): void {
            $prompt = Prompt::query()->lockForUpdate()->findOrFail($prompt->id);

            foreach ($prompt->generationOperations()->lockForUpdate()->get() as $operation) {
                if (! $operation->status->isActive()) {
                    continue;
                }

                $operation->update(['cancel_requested_at' => now(),
                    'status' => $operation->status === GenerationOperationStatus::PLANNED && $operation->stage !== GenerationOperationStage::DOWNLOAD
                        ? GenerationOperationStatus::CANCELLED : $operation->status]);
            }
        });
    }
}
