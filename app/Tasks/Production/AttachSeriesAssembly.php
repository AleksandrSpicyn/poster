<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Models\SeriesAssembly;
use App\Repositories\ProductionRunRepository;
use Illuminate\Support\Facades\DB;

readonly class AttachSeriesAssembly
{
    public function __construct(private ProductionRunRepository $runs) {}

    public function run(SeriesAssembly $assembly): void
    {
        DB::transaction(function () use ($assembly): void {
            $run = $this->runs->cycleForAssembly($assembly);

            if (! $run instanceof ProductionRun || ! in_array($run->stage, [ProductionStage::FINAL_MONTAGE, ProductionStage::FINAL_REVIEW], true)) {
                return;
            }

            $this->runs->update($run, ['series_assembly_id' => $assembly->id, 'stage' => ProductionStage::FINAL_MONTAGE,
                'stage_started_at' => now(), 'next_check_at' => now(), 'status' => ProductionStatus::WAITING,
                'lock_version' => $run->lock_version + 1, 'error_message' => null]);
        });
    }
}
