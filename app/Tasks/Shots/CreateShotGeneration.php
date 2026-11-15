<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Story\Snapshot;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\Series;
use App\Models\Shot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateShotGeneration
{
    /** @param array<string, mixed> $snapshot */
    public function run(Shot $shot, array $snapshot, ?string $requestKey, bool $newTake, ?ProductionRun $productionRun = null): Prompt
    {
        return DB::transaction(function () use ($shot, $snapshot, $requestKey, $newTake, $productionRun): Prompt {
            $seriesId = $snapshot['series_id'] ?? null;

            if (! is_int($seriesId)) {
                throw ValidationException::withMessages(['series' => 'The generation context has no series identity.']);
            }

            $series = Series::query()->findOrFail($seriesId);
            $project = Project::query()->lockForUpdate()->findOrFail($series->project_id);
            $series = Series::query()->lockForUpdate()->findOrFail($seriesId);

            if (! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before generating shots.']);
            }

            if ($productionRun instanceof ProductionRun) {
                ProductionRun::query()->lockForUpdate()->findOrFail($productionRun->id);
            }

            $shot = Shot::query()->lockForUpdate()->findOrFail($shot->id);

            if ($series->specification_version !== $snapshot['specification_version'] || $series->continuity_version !== $snapshot['continuity_version']) {
                throw ValidationException::withMessages(['shot' => 'The story changed before the generation request was saved.']);
            }

            if ($requestKey !== null) {
                $existing = GenerationOperation::query()->where('request_key', $requestKey)->first();

                if ($existing !== null) {
                    $prompt = $existing->prompt()->firstOrFail();

                    if ($prompt->shot_id !== $shot->id) {
                        throw ValidationException::withMessages(['request_key' => 'The request key is already used by another shot.']);
                    }

                    if ($prompt->input_hash !== Snapshot::hash([...$snapshot, 'request_key' => $requestKey])) {
                        throw ValidationException::withMessages(['request_key' => 'This request key belongs to different generation inputs. Start a new request for changed inputs.']);
                    }

                    return $prompt->load('generationOperations');
                }
            }

            $latest = $shot->prompts()->latest('take_number')->first();

            if ($latest !== null && ! $newTake) {
                return $latest->load('generationOperations');
            }

            if ($latest !== null && $latest->generationOperations()->whereIn('status', [GenerationOperationStatus::PLANNED->value, GenerationOperationStatus::SUBMITTING->value, GenerationOperationStatus::SUBMITTED->value, GenerationOperationStatus::RUNNING->value, GenerationOperationStatus::SUBMISSION_UNKNOWN->value])->exists()) {
                throw ValidationException::withMessages(['shot' => 'Finish or resolve the active generation before starting another paid take.']);
            }

            if (($latest->take_number ?? 0) >= 10) {
                throw ValidationException::withMessages(['shot' => 'The shot has reached its ten-take limit. Revise the screenplay before generating more.']);
            }

            $requestKey ??= (string) Str::uuid();
            $snapshot['request_key'] = $requestKey;
            $projectId = $series->project_id;
            $prompt = new Prompt(['prompt' => $snapshot['video_prompt'], 'shot_id' => $shot->id,
                'take_number' => ($latest->take_number ?? 0) + 1, 'input_snapshot' => $snapshot,
                'input_hash' => Snapshot::hash($snapshot)]);
            $prompt->project_id = $projectId;
            $prompt->save();
            $prompt->generationOperations()->create([
                'production_run_id' => $productionRun?->id,
                'stage' => GenerationOperationStage::IMAGE, 'status' => GenerationOperationStatus::PLANNED,
                'request_key' => $requestKey, 'idempotency_key' => hash('sha256', $requestKey . ':image'),
                'request_snapshot' => $snapshot, 'next_check_at' => now(),
            ]);

            return $prompt->load('generationOperations');
        });
    }
}
