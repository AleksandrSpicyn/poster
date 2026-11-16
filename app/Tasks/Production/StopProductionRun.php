<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\PromptRepository;
use App\Repositories\PublicationRequestRepository;
use Illuminate\Support\Facades\DB;

class StopProductionRun
{
    public function __construct(
        private readonly ProductionRunRepository $runs,
        private readonly GenerationOperationRepository $operations,
        private readonly PromptRepository $prompts,
        private readonly PublicationRequestRepository $publications,
    ) {}

    public function run(ProductionRun $run, ?string $message = null): ProductionRun
    {
        return DB::transaction(function () use ($run, $message): ProductionRun {
            $run = $this->runs->findLockedOrFail($run->id);

            if (! $run->status->blocksNextRun()) {
                return $run;
            }

            $this->runs->update($run, ['status' => ProductionStatus::STOPPED, 'next_check_at' => null, 'completed_at' => now(), 'error_message' => $message]);

            if ($run->episode_id !== null) {
                $prompts = $this->prompts->lockEpisodeTakes($run);

                foreach ($prompts as $prompt) {
                    foreach ($this->operations->lockForPrompt($prompt) as $operation) {
                        if (! $operation->status->isActive()) {
                            continue;
                        }

                        $this->operations->update($operation, ['cancel_requested_at' => now(),
                            'status' => $operation->status === GenerationOperationStatus::PLANNED && $operation->stage !== GenerationOperationStage::DOWNLOAD
                                ? GenerationOperationStatus::CANCELLED : $operation->status]);
                    }
                }
            }

            if ($run->episode_assembly_id !== null) {
                $this->publications->failPendingProduction($run);
            }

            return $run;
        });
    }
}
