<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Actions\Shots\PrepareShotGenerationInputSubAction;
use App\Data\Episodes\AssemblyManifestData;
use App\Data\Episodes\AssemblyShotData;
use App\Enums\AssemblyStatus;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\TransitionMode;
use App\Jobs\BuildEpisodeAssemblyJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Scene;
use App\Models\User;
use App\Tasks\Episodes\SaveEpisodeAssembly;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Shots\AssertShotGenerationContext;
use App\Tasks\Shots\CreateShotGeneration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

readonly class ProduceSeriesMediaSubAction
{
    public function __construct(
        private UpdateProductionRun $update,
        private AssertShotGenerationContext $shotContext,
        private PrepareShotGenerationInputSubAction $shotInput,
        private CreateShotGeneration $createTake,
        private SaveEpisodeAssembly $saveAssembly,
    ) {}

    public function run(ProductionRun $run, User $actor): void
    {
        $episode = $run->episode ?? throw new RuntimeException('The production episode is unavailable.');
        $revision = $episode->currentRevision ?? throw new RuntimeException('The production screenplay is unavailable.');
        $shots = $revision->scenes->flatMap(fn(Scene $scene) => $scene->shots)->values();
        $selections = [];
        $previousPromptId = null;

        foreach ($shots as $shot) {
            $latest = $shot->prompts->sortByDesc('take_number')->first();

            if ($latest !== null && $latest->operations->contains(fn(GenerationOperation $operation): bool => $operation->status === GenerationOperationStatus::SUBMISSION_UNKNOWN)) {
                $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: 'A generation result is unknown. Resolve the existing provider request before another paid submission.');

                return;
            }

            $ready = $shot->prompts->where('generated', true)->sortByDesc('take_number')->first();

            if ($ready !== null) {
                $selections[] = new AssemblyShotData($ready->id, 0, $shot->duration_ms);
                $previousPromptId = $ready->id;

                continue;
            }

            if ($latest !== null) {
                $blocked = $latest->operations->contains(fn(GenerationOperation $operation): bool => in_array($operation->status, [
                    GenerationOperationStatus::FAILED,
                    GenerationOperationStatus::CANCELLED,
                    GenerationOperationStatus::ABANDONED,
                ], true));
                $this->update->run(
                    $run,
                    $blocked ? ProductionStatus::NEEDS_ATTENTION : ProductionStatus::WAITING,
                    message: $blocked ? 'A shot stopped or failed. Review the existing take before requesting another generation.' : null,
                );

                return;
            }

            $shot = $this->shotContext->run($shot);
            $source = $shot->transition_mode === TransitionMode::NEW_SCENE ? null : $previousPromptId;
            $snapshot = $this->shotInput->run($shot, $actor, $source);
            $take = $this->createTake->run($shot, $snapshot, 'production-' . $run->id . '-shot-' . $shot->id, false, $run);
            $operation = $take->generationOperations->first(fn(GenerationOperation $operation): bool => $operation->stage === GenerationOperationStage::IMAGE && $operation->status === GenerationOperationStatus::PLANNED);

            if ($operation !== null) {
                SubmitShotGenerationJob::dispatch($operation->id)->afterCommit();
            }

            $this->update->run($run, ProductionStatus::WAITING);

            return;
        }

        DB::transaction(function () use ($revision, $selections, $run): void {
            $assembly = $this->saveAssembly->run($revision, new AssemblyManifestData($selections));
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::MONTAGE, attributes: ['episode_assembly_id' => $assembly->id]);

            if ($assembly->status === AssemblyStatus::QUEUED) {
                BuildEpisodeAssemblyJob::dispatch($assembly->id)->afterCommit();
            }
        });
    }
}
