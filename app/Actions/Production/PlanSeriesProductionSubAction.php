<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionStage;
use App\Enums\ProductionStatus;
use App\Models\ProductionRun;
use App\Tasks\Production\UpdateProductionRun;
use App\Tasks\Series\AssertCyclePlan;
use App\Tasks\Series\GenerateCyclePlan;
use App\Tasks\Series\PrepareCyclePlan;
use App\Tasks\Series\SaveCyclePlan;

readonly class PlanSeriesProductionSubAction
{
    public function __construct(private PrepareCyclePlan $prepare, private GenerateCyclePlan $generate, private AssertCyclePlan $assert, private SaveCyclePlan $save, private UpdateProductionRun $update) {}

    public function run(ProductionRun $run): void
    {
        if ($run->series?->cycle_plan === null) {
            $plan = $this->generate->run($this->prepare->run($run));
            $this->assert->run($plan, $run);
            $this->save->run($run, $plan);
        }

        $this->update->run($run, ProductionStatus::WAITING, ProductionStage::PREPARING);
    }
}
