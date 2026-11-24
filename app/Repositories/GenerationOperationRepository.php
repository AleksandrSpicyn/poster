<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Shots\ShotOperationClaimData;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Events\WorkspaceUpdated;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;

/** @extends EloquentRepository<GenerationOperation> */
readonly class GenerationOperationRepository extends EloquentRepository
{
    protected function model(): string
    {
        return GenerationOperation::class;
    }

    /** @return Collection<int, GenerationOperation> */
    public function forReconciliation(int $limit): Collection
    {
        return GenerationOperation::query()->with('prompt.media', 'prompt.project')
            ->where(function ($query): void {
                $query->whereIn('status', [
                    GenerationOperationStatus::PLANNED->value,
                    GenerationOperationStatus::SUBMITTED->value,
                    GenerationOperationStatus::RUNNING->value,
                ])
                    ->where(function ($query): void {
                        $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
                    });
            })
            ->orWhere(function ($query): void {
                $query->where('status', GenerationOperationStatus::SUBMITTING)->where('updated_at', '<', now()->subMinutes(10));
            })
            ->orderBy('id')->limit(max(1, min($limit, 100)))->get();
    }

    /** @return Collection<int, GenerationOperation> */
    public function lockForPrompt(Prompt $prompt): Collection
    {
        return $prompt->generationOperations()->with('prompt.project')->lockForUpdate()->get();
    }

    /**
     * @param iterable<GenerationOperation> $operations Locked operations with their workspace ownership loaded.
     * @return list<int> Takes stopped before submission.
     */
    public function cancelActive(iterable $operations): array
    {
        $changed = [];
        $planned = [];
        $stoppedTakes = [];
        $submitted = [];
        $now = now();

        foreach ($operations as $operation) {
            if ($operation->status === GenerationOperationStatus::FAILED && $operation->stage === GenerationOperationStage::DOWNLOAD) {
                $stoppedTakes[] = $operation->prompt_id;
            }

            if (! $operation->status->isActive()) {
                continue;
            }

            $operation->cancel_requested_at = $now;

            if ($operation->status === GenerationOperationStatus::PLANNED && $operation->stage !== GenerationOperationStage::DOWNLOAD) {
                $operation->status = GenerationOperationStatus::CANCELLED;
                $planned[] = $operation->id;
                $stoppedTakes[] = $operation->prompt_id;
            } elseif ($operation->isDirty('cancel_requested_at')) {
                $submitted[] = $operation->id;
            } else {
                continue;
            }

            $changed[] = $operation;
        }

        if ($planned !== []) {
            GenerationOperation::query()->whereKey($planned)->update(['cancel_requested_at' => $now, 'status' => GenerationOperationStatus::CANCELLED]);
        }

        if ($submitted !== []) {
            GenerationOperation::query()->whereKey($submitted)->update(['cancel_requested_at' => $now]);
        }

        foreach ($changed as $operation) {
            WorkspaceUpdated::forResource($operation);
        }

        return $stoppedTakes;
    }

    public function forRequestKey(string $requestKey): ?GenerationOperation
    {
        return GenerationOperation::query()->where('request_key', $requestKey)->first();
    }

    public function hasUnresolvedForPrompt(Prompt $latest): bool
    {
        return $latest->generationOperations()->whereIn('status', [
            GenerationOperationStatus::PLANNED->value,
            GenerationOperationStatus::SUBMITTING->value,
            GenerationOperationStatus::SUBMITTED->value,
            GenerationOperationStatus::RUNNING->value,
            GenerationOperationStatus::SUBMISSION_UNKNOWN->value,
        ])->exists();
    }

    /** @param array<string, mixed> $snapshot */
    public function createImageOperation(Prompt $prompt, ?ProductionRun $productionRun, string $requestKey, array $snapshot): GenerationOperation
    {
        return $prompt->generationOperations()->chaperone('prompt')->create([
            'production_run_id' => $productionRun?->id,
            'stage' => GenerationOperationStage::IMAGE,
            'status' => GenerationOperationStatus::PLANNED,
            'request_key' => $requestKey,
            'idempotency_key' => hash('sha256', $requestKey . ':image'),
            'request_snapshot' => $snapshot,
            'next_check_at' => now(),
        ]);
    }

    public function lockDownloadForPrompt(Prompt $prompt): GenerationOperation
    {
        return $prompt->generationOperations()->where('stage', GenerationOperationStage::DOWNLOAD)->lockForUpdate()->firstOrFail();
    }

    public function forResolution(int $id): GenerationOperation
    {
        return GenerationOperation::query()->with('prompt.shot.scene.episodeRevision.episode.series')->findOrFail($id);
    }

    /** Перед платной отправкой порядок блокировок: Project → Run → Prompt → Operation. */
    public function lockClaimContext(int $operationId, bool $polling): ShotOperationClaimData
    {
        $operation = GenerationOperation::query()->findOrFail($operationId);
        $project = ! $polling && $operation->stage !== GenerationOperationStage::DOWNLOAD
            ? Project::query()->lockForUpdate()->find($operation->prompt()->firstOrFail()->project_id) : null;
        $series = null;

        if (! $polling && $operation->stage !== GenerationOperationStage::DOWNLOAD) {
            $source = $operation->prompt()->with('shot.scene.episodeRevision.episode.series')->firstOrFail();
            $seriesId = $source->shot?->scene?->episodeRevision?->episode?->series_id;
            $series = $seriesId === null ? null : Series::query()->lockForUpdate()->find($seriesId);
        }

        $productionRun = ! $polling && $operation->stage !== GenerationOperationStage::DOWNLOAD && $operation->production_run_id !== null
            ? ProductionRun::query()->lockForUpdate()->find($operation->production_run_id) : null;
        $prompt = Prompt::query()->lockForUpdate()->findOrFail($operation->prompt_id);
        $operation = GenerationOperation::query()->lockForUpdate()->findOrFail($operationId);
        $productionRun?->load('series');

        return new ShotOperationClaimData($operation, $project, $productionRun, $prompt, $series);
    }

    public function withPromptShot(GenerationOperation $operation): GenerationOperation
    {
        return $operation->load('prompt.shot');
    }

    public function createNextStage(Prompt $prompt, GenerationOperation $operation, GenerationOperationStage $nextStage): GenerationOperation
    {
        return $prompt->generationOperations()->firstOrCreate(['stage' => $nextStage], [
            'production_run_id' => $operation->production_run_id,
            'status' => GenerationOperationStatus::PLANNED,
            'request_key' => $operation->request_key,
            'idempotency_key' => hash('sha256', $operation->request_key . ':' . $nextStage->value),
            'provider' => $operation->provider,
            'model' => $operation->model,
            'request_snapshot' => $operation->request_snapshot,
            'result_snapshot' => $operation->result_snapshot,
            'cancel_requested_at' => $operation->cancel_requested_at,
            'next_check_at' => now(),
        ]);
    }

    public function lockForPromptOperation(Prompt $prompt, int $id): GenerationOperation
    {
        return $prompt->generationOperations()->lockForUpdate()->findOrFail($id);
    }
}
