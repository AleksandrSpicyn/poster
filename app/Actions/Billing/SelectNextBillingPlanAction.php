<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\NextBillingPlanData;
use App\Repositories\BillingWorkspaceRepository;

final readonly class SelectNextBillingPlanAction
{
    public function __construct(private BillingWorkspaceRepository $workspace) {}

    public function run(int $ownerId, NextBillingPlanData $data): void
    {
        $this->workspace->setNextPlan($ownerId, $data);
    }
}
