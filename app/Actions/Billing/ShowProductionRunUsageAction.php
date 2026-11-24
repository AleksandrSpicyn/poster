<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\GenerationUsageData;
use App\Enums\GenerationAccountingMode;
use App\Models\ProductionRun;
use App\Models\User;
use App\Repositories\BillingAccountingRepository;
use App\Repositories\BillingWorkspaceRepository;

final readonly class ShowProductionRunUsageAction
{
    public function __construct(
        private BillingAccountingRepository $accounting,
        private BillingWorkspaceRepository $workspace,
    ) {}

    public function run(User $owner, ProductionRun $run): GenerationUsageData
    {
        $quote = $this->workspace->runQuote($owner->id, $run->id);

        return new GenerationUsageData(
            enforced: $this->accounting->storedMode($owner->id) === GenerationAccountingMode::ENFORCED->value,
            estimate: $quote['estimate'],
            available: $this->workspace->balance($owner->id)->available,
            sources: $this->workspace->usage($owner->id, null, $run->id),
            shots: $quote['shots'],
        );
    }
}
