<?php

declare(strict_types=1);

namespace App\Actions\Shots;

use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Jobs\CheckShotGenerationJob;
use App\Jobs\SubmitShotGenerationJob;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Tasks\Shots\GetGenerationOperationsForReconciliation;
use App\Tasks\Shots\UpdateGenerationOperation;

readonly class ReconcileShotGenerationsAction
{
    public function __construct(private GetGenerationOperationsForReconciliation $operations, private UpdateGenerationOperation $update) {}

    public function run(int $limit = 50): int
    {
        $operations = $this->operations->run($limit);

        foreach ($operations as $operation) {
            if ($operation->status === GenerationOperationStatus::SUBMITTING) {
                $image = $operation->prompt?->getFirstMedia(Prompt::SHOT_IMAGE);

                if ($operation->stage === GenerationOperationStage::IMAGE && $image !== null
                    && is_file($image->getPath()) && @getimagesize($image->getPath()) !== false) {
                    $next = $this->update->run(
                        $operation,
                        GenerationOperationStatus::SUCCEEDED,
                        ['result_snapshot' => ['image_media_id' => $image->id], 'error_code' => 'recovered_local_image_receipt'],
                    );

                    if ($next instanceof GenerationOperation) {
                        SubmitShotGenerationJob::dispatch($next->id)->afterCommit();
                    }

                    continue;
                }

                $this->update->run(
                    $operation,
                    $operation->stage === GenerationOperationStage::DOWNLOAD
                    ? GenerationOperationStatus::PLANNED : GenerationOperationStatus::SUBMISSION_UNKNOWN,
                    ['error_code' => 'worker_interrupted_after_claim'],
                );
            } elseif ($operation->status === GenerationOperationStatus::PLANNED) {
                SubmitShotGenerationJob::dispatch($operation->id)->afterCommit();
            } else {
                CheckShotGenerationJob::dispatch($operation->id)->afterCommit();
            }
        }

        return $operations->count();
    }
}
