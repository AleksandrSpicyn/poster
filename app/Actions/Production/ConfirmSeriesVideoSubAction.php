<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\AssemblyStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Series\GetSeriesVideo;

readonly class ConfirmSeriesVideoSubAction
{
    public function __construct(private GetSeriesVideo $get, private UpdateProductionRun $update) {}

    public function run(ProductionRun $run): void
    {
        if ($run->series_assembly_id === null) {
            return;
        }

        $assembly = $this->get->run($run->series_assembly_id);

        if ($assembly->status === AssemblyStatus::ACCEPTED && $assembly->series->final_assembly_id === $assembly->id) {
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::PUBLICATION);
        }
    }
}
