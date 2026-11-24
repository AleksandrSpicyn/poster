<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Repositories\ProjectRepository;
use App\Repositories\SeriesAssemblyRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class StartSeriesAssembly
{
    public function __construct(private ProjectRepository $projects, private SeriesRepository $series, private SeriesAssemblyRepository $assemblies) {}

    public function run(SeriesAssembly $assembly): ?SeriesAssembly
    {
        return DB::transaction(function () use ($assembly): ?SeriesAssembly {
            $context = $this->assemblies->withContext($assembly->id);
            $project = $this->projects->findLockedOrFail($context->series->project_id);
            $series = $this->series->findLockedOrFail($context->series_id);
            $assembly = $this->assemblies->findLockedOrFail($assembly->id);

            if (! $project->is_active || $series->cycle_paused_at !== null || $series->archived_at !== null) {
                throw ValidationException::withMessages(['assembly' => 'The cycle is stopped or archived.']);
            }

            if ($assembly->status !== AssemblyStatus::QUEUED) {
                return null;
            }

            $this->assemblies->update($assembly, [
                'status' => AssemblyStatus::BUILDING,
                'started_at' => now(),
                'lock_version' => $assembly->lock_version + 1,
            ]);

            return $assembly;
        });
    }
}
