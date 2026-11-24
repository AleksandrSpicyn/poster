<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Story\Snapshot;
use App\Enums\SeriesOutputMode;
use App\Models\GenerationOperation;
use App\Models\ProductionRun;
use App\Models\Prompt;
use App\Models\Shot;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\PromptRepository;
use App\Repositories\SeriesRepository;
use App\Repositories\ShotRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateShotGeneration
{
    public function __construct(
        private readonly GenerationReservationRepository $usage,
        private readonly ProjectRepository $projects,
        private readonly SeriesRepository $series,
        private readonly ShotRepository $shots,
        private readonly ProductionRunRepository $runs,
        private readonly PromptRepository $prompts,
        private readonly GenerationOperationRepository $operations,
    ) {}

    /** @param array<string, mixed> $snapshot */
    public function run(Shot $shot, array $snapshot, ?string $requestKey, bool $newTake, ?ProductionRun $productionRun = null): Prompt
    {
        return DB::transaction(function () use ($shot, $snapshot, $requestKey, $newTake, $productionRun): Prompt {
            $seriesId = $snapshot['series_id'] ?? null;

            if (! is_int($seriesId)) {
                throw ValidationException::withMessages(['series' => 'The generation context has no series identity.']);
            }

            $series = $this->series->findOrFail($seriesId);
            $project = $this->projects->findLockedOrFail($series->project_id);
            $series = $this->series->findLockedOrFail($seriesId);

            if (! $project->is_active) {
                throw ValidationException::withMessages(['project' => 'Enable this project before generating shots.']);
            }

            if ($productionRun instanceof ProductionRun) {
                $this->runs->findLockedOrFail($productionRun->id);
            }

            $shot = $this->shots->findLockedOrFail($shot->id);

            if ($series->specification_version !== $snapshot['specification_version'] || $series->continuity_version !== $snapshot['continuity_version']) {
                throw ValidationException::withMessages(['shot' => 'The story changed before the generation request was saved.']);
            }

            if ($requestKey !== null) {
                $existing = $this->operations->forRequestKey($requestKey);

                if ($existing instanceof GenerationOperation) {
                    $prompt = $this->prompts->forOperation($existing);

                    if ($prompt->shot_id !== $shot->id) {
                        throw ValidationException::withMessages(['request_key' => 'The request key is already used by another shot.']);
                    }

                    if ($prompt->input_hash !== Snapshot::hash([...$snapshot, 'request_key' => $requestKey])) {
                        throw ValidationException::withMessages(['request_key' => 'This request key belongs to different generation inputs. Start a new request for changed inputs.']);
                    }

                    return $this->prompts->withGenerationOperations($prompt);
                }
            }

            $latest = $this->prompts->latestForShot($shot);

            if ($latest instanceof Prompt && ! $newTake) {
                return $this->prompts->withGenerationOperations($latest);
            }

            if ($latest instanceof Prompt && $this->operations->hasUnresolvedForPrompt($latest)) {
                throw ValidationException::withMessages(['shot' => 'Finish or resolve the active generation before starting another paid take.']);
            }

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO
                && ($series->cycle_paused_at !== null || $this->prompts->cycleTakeCount($series->id) >= 200)) {
                throw ValidationException::withMessages(['shot' => 'The cycle is paused or has reached its 200-take budget.']);
            }

            if (($latest->take_number ?? 0) >= 10) {
                throw ValidationException::withMessages(['shot' => 'The shot has reached its ten-take limit. Revise the screenplay before generating more.']);
            }

            $requestKey ??= (string) Str::uuid();
            $snapshot['request_key'] = $requestKey;
            $prompt = new Prompt([
                'prompt' => $snapshot['video_prompt'],
                'shot_id' => $shot->id,
                'take_number' => ($latest->take_number ?? 0) + 1,
                'input_snapshot' => $snapshot,
                'input_hash' => Snapshot::hash($snapshot),
            ]);
            $prompt->project()->associate($project);

            $this->prompts->save($prompt);
            $this->usage->reserveTake($project, $prompt, $productionRun?->id);
            $this->operations->createImageOperation($prompt, $productionRun, $requestKey, $snapshot);

            return $this->prompts->withGenerationOperations($prompt);
        });
    }
}
