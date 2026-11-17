<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Tasks\Production\AdvanceSeriesCursor;
use App\Tasks\Production\UpdateProductionRun;

readonly class AdvanceSeriesCycleSubAction
{
    public function __construct(private AdvanceSeriesCursor $cursor, private UpdateProductionRun $update) {}

    public function run(ProductionRun $run): void
    {
        if ($run->episode?->number < data_get($run->input_snapshot, 'episode_limit')) {
            $this->cursor->run($run);

            return;
        }

        $this->update->run($run, ProductionStatus::WAITING, ProductionStage::FINAL_MONTAGE);
    }
}
