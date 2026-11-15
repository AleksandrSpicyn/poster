<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Enums\ProjectContentMode;
use App\Models\ProductionRun;
use App\Models\Project;
use App\Models\Series;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductionRun
{
    public function run(Project $project, ?Series $series, User $actor, string $requestKey, bool $onlyFirstRun = false): ?ProductionRun
    {
        return DB::transaction(function () use ($project, $series, $actor, $requestKey, $onlyFirstRun): ?ProductionRun {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $series = $series instanceof Series ? $project->series()->lockForUpdate()->findOrFail($series->id) : null;

            if (($project->content_mode === ProjectContentMode::SERIES) !== ($series !== null)) {
                throw ValidationException::withMessages(['project' => 'The project content type changed. Refresh before starting production.']);
            }

            $key = hash('sha256', $project->id . ':' . ($series->id ?? 'short') . ':' . $requestKey);
            $existing = ProductionRun::query()->where('request_key', $key)->first();

            if ($existing !== null) {
                return $existing;
            }

            $active = $project->productionRuns()->where('series_id', $series?->id)
                ->whereIn('status', ProductionStatus::blockingValues())->first();

            if ($active !== null) {
                return $active;
            }

            if ($series !== null) {
                if ($onlyFirstRun && $series->productionRuns()->exists()) {
                    return null;
                }

                if (! $series->automation_enabled || $series->archived_at !== null) {
                    return null;
                }

                $latest = $series->episodes()->orderByDesc('number')->first();

                if ($series->episode_limit !== null && $latest?->accepted_assembly_id !== null && $latest->number >= $series->episode_limit) {
                    return null;
                }
            }

            return $project->productionRuns()->create([
                'series_id' => $series?->id, 'actor_id' => $actor->id,
                'request_key' => $key, 'status' => ProductionStatus::QUEUED,
                'stage' => ProductionStage::PREPARING, 'next_check_at' => now(),
            ]);
        });
    }
}
