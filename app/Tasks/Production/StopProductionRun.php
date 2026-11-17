<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PromptRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;

class StopProductionRun
{
    public function __construct(
        private readonly ProductionRunRepository $runs,
        private readonly ProjectRepository $projects,
        private readonly SeriesRepository $series,
        private readonly GenerationOperationRepository $operations,
        private readonly PromptRepository $prompts,
        private readonly PublicationRequestRepository $publications,
    ) {}

    public function run(ProductionRun $run, ?string $message = null): ProductionRun
    {
        return DB::transaction(function () use ($run, $message): ProductionRun {
            $this->projects->findLockedOrFail($run->project_id);

            if ($run->series_id !== null) {
                $this->series->findLockedOrFail($run->series_id);
            }

            $run = $this->runs->findLockedOrFail($run->id);

            if (! $run->status->blocksNextRun()) {
                return $run;
            }

            $this->series->pauseCycle($run);

            $this->runs->update($run, ['status' => ProductionStatus::STOPPED, 'next_check_at' => null, 'completed_at' => now(), 'error_message' => $message]);

            if ($run->series_id !== null) {
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

            if ($run->episode_assembly_id !== null || $run->series_assembly_id !== null) {
                $this->publications->failPendingProduction($run);
            }

            return $run;
        });
    }
}
