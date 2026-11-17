<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Models\Series;
use App\Models\User;
use App\Repositories\ProductionRunRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class ResumeProduction
{
    public function __construct(private ProjectRepository $projects, private SeriesRepository $series, private ProductionRunRepository $runs) {}

    public function run(ProductionRun $run, User $actor): ProductionRun
    {
        return DB::transaction(function () use ($run, $actor): ProductionRun {
            $project = $this->projects->findLockedOrFail($run->project_id);
            $series = $run->series_id === null ? null : $this->series->findLockedOrFail($run->series_id);
            $current = $this->runs->findLockedOrFail($run->id);

            if ($current->status === ProductionStatus::STOPPED) {
                $existing = $this->runs->forRequestKey(hash('sha256', 'resume:' . $current->id));

                if ($existing instanceof ProductionRun) {
                    return $existing;
                }
            }

            if (! $project->is_active || ! $series instanceof Series || ! $series->automation_enabled || $series->archived_at !== null
                || ! in_array($current->status, [ProductionStatus::STOPPED, ProductionStatus::NEEDS_ATTENTION], true)
                || $this->runs->hasUnresolvedEffects($current)) {
                throw ValidationException::withMessages(['production' => 'Enable the cycle and resolve all submitted or unknown effects before resuming.']);
            }

            if ($current->status === ProductionStatus::STOPPED) {
                $key = hash('sha256', 'resume:' . $current->id);
                $existing = $this->runs->forRequestKey($key) ?? $this->runs->blockingForProject($project, $series);

                if ($existing instanceof ProductionRun) {
                    return $existing;
                }

                $this->series->update($series, ['cycle_paused_at' => null]);
                $next = $this->runs->createQueued($project, $series, $actor, $key);

                if ($current->series_assembly_id !== null) {
                    $this->runs->update($next, ['series_assembly_id' => $current->series_assembly_id, 'stage' => $current->stage,
                        'episode_id' => $current->episode_id, 'episode_assembly_id' => $current->episode_assembly_id]);
                }

                return $next;
            }

            $this->runs->update($current, ['status' => ProductionStatus::WAITING, 'next_check_at' => now(),
                'stage_started_at' => now(), 'lock_version' => $current->lock_version + 1, 'error_message' => null]);

            return $current;
        });
    }
}
