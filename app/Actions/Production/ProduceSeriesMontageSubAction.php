<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\AssemblyStatus;
use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use RuntimeException;

readonly class ProduceSeriesMontageSubAction
{
    public function __construct(
        private UpdateProductionRun $update,
    ) {}

    public function run(ProductionRun $run): void
    {
        $assembly = $run->assembly ?? throw new RuntimeException('The production montage is unavailable.');

        if (in_array($assembly->status, [AssemblyStatus::READY, AssemblyStatus::ACCEPTED], true)) {
            $this->update->run($run, ProductionStatus::WAITING, ProductionStage::STORY);

            return;
        }

        $failed = $assembly->status === AssemblyStatus::FAILED;
        $this->update->run(
            $run,
            $failed ? ProductionStatus::NEEDS_ATTENTION : ProductionStatus::WAITING,
            message: $failed ? 'The montage could not be built. Review its error before resuming.' : null,
        );
    }
}
