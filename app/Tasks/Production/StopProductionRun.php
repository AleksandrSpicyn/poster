<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Models\Prompt;
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
            $project = $this->projects->findLockedOrFail($run->project_id);
            $series = $run->series_id === null ? null : $this->series->findLockedOrFail($run->series_id);
            $run = $this->runs->findLockedOrFail($run->id);

            if (! $run->status->blocksNextRun()) {
                return $run;
            }

            $run->setRelation('project', $project);
            $series?->setRelation('project', $project);
            $this->series->pauseCycle($series);

            $this->runs->update($run, ['status' => ProductionStatus::STOPPED, 'next_check_at' => null, 'completed_at' => now(), 'error_message' => $message]);

            if ($run->series_id !== null) {
                $prompts = $this->prompts->lockEpisodeTakes($run);

                $this->operations->cancelActive($prompts->flatMap(fn(Prompt $prompt) => $prompt->generationOperations));
            }

            if ($run->episode_assembly_id !== null || $run->series_assembly_id !== null) {
                $this->publications->failPendingProduction($run);
            }

            return $run;
        });
    }
}
