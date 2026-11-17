<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\CyclePlanData;
use App\Models\ProductionRun;
use App\Repositories\ProductionRunRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

readonly class SaveCyclePlan
{
    public function __construct(private SeriesRepository $series, private ProductionRunRepository $runs) {}

    public function run(ProductionRun $run, CyclePlanData $plan): void
    {
        DB::transaction(function () use ($run, $plan): void {
            $series = $this->series->findLockedOrFail($run->series_id ?? throw new RuntimeException('The series is missing.'));
            $current = $this->runs->findLockedOrFail($run->id);

            if (! $current->status->isActive() || $series->cycle_paused_at !== null || $series->specification_version !== data_get($run->input_snapshot, 'specification_version')) {
                throw ValidationException::withMessages(['cycle_plan' => 'The cycle changed while its plan was being generated.']);
            }

            if ($series->cycle_plan === null) {
                $this->series->update($series, ['cycle_plan' => $plan->toArray()]);
            }
        });
    }
}
