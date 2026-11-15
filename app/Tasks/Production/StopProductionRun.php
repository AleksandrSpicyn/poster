<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStatus;
use App\Enums\PublicationStatus;
use App\Models\ProductionRun;
use App\Models\Prompt;
use App\Models\PublicationRequest;
use Illuminate\Support\Facades\DB;

class StopProductionRun
{
    public function run(ProductionRun $run, ?string $message = null): ProductionRun
    {
        return DB::transaction(function () use ($run, $message): ProductionRun {
            $run = ProductionRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $run->status->blocksNextRun()) {
                return $run;
            }

            $run->update(['status' => ProductionStatus::STOPPED, 'next_check_at' => null, 'completed_at' => now(), 'error_message' => $message]);

            if ($run->episode_id !== null) {
                $prompts = Prompt::query()->whereHas('shot.scene.episodeRevision', fn($query) => $query->where('episode_id', $run->episode_id))
                    ->lockForUpdate()->get();

                foreach ($prompts as $prompt) {
                    foreach ($prompt->generationOperations()->lockForUpdate()->get() as $operation) {
                        if (! $operation->status->isActive()) {
                            continue;
                        }

                        $operation->update(['cancel_requested_at' => now(),
                            'status' => $operation->status === GenerationOperationStatus::PLANNED && $operation->stage !== GenerationOperationStage::DOWNLOAD
                                ? GenerationOperationStatus::CANCELLED : $operation->status]);
                    }
                }
            }

            if ($run->episode_assembly_id !== null) {
                PublicationRequest::query()->where('episode_assembly_id', $run->episode_assembly_id)
                    ->where('production_run_id', $run->id)
                    ->whereIn('status', [PublicationStatus::PLANNED, PublicationStatus::PROCESSING])
                    ->update(['status' => PublicationStatus::FAILED, 'error_message' => 'Automatic production stopped before publication.']);
            }

            return $run;
        });
    }
}
