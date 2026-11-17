<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\AssemblyStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Jobs\BuildSeriesAssemblyJob;
use App\Models\ProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Series\SaveSeriesAssembly;
use RuntimeException;

readonly class BuildSeriesVideoSubAction
{
    public function __construct(private SaveSeriesAssembly $save, private UpdateProductionRun $update) {}

    public function run(ProductionRun $run): void
    {
        $assembly = $run->seriesAssembly;

        if ($assembly === null) {
            $assembly = $this->save->run($run->series ?? throw new RuntimeException('The cycle is unavailable.'));
            $this->update->run($run, ProductionStatus::WAITING, attributes: ['series_assembly_id' => $assembly->id]);
        }

        if ($assembly->status === AssemblyStatus::QUEUED) {
            BuildSeriesAssemblyJob::dispatch($assembly->id)->afterCommit();
        }

        if ($assembly->status === AssemblyStatus::FAILED) {
            $this->update->run($run, ProductionStatus::NEEDS_ATTENTION, message: $assembly->error_message);

            return;
        }

        if (in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
            $this->update->run(
                $run,
                ProductionStatus::WAITING,
                ProductionStage::FINAL_REVIEW,
                message: 'Review the complete video and every boundary with sound before accepting it.',
            );
        }
    }
}
