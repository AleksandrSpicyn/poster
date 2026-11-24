<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\GenerationAccountingMode;
use App\Models\ProductionRun;
use App\Models\User;
use App\Repositories\BillingAccountingRepository;
use App\Repositories\BillingWorkspaceRepository;
use Illuminate\Support\Collection;
use stdClass;

final readonly class ShowProductionRunUsageAction
{
    public function __construct(
        private BillingAccountingRepository $accounting,
        private BillingWorkspaceRepository $workspace,
    ) {}

    /** @return array{enforced: bool, estimate: ?int, shots: list<array{shot_id: int, prompt_id: ?int, seconds: int, status: string}>, available: int, sources: Collection<int, stdClass>} */
    public function run(User $owner, ProductionRun $run): array
    {
        return [
            'enforced' => $this->accounting->storedMode($owner->id) === GenerationAccountingMode::ENFORCED->value,
            ...$this->workspace->runQuote($owner->id, $run->id),
            'available' => $this->workspace->balance($owner->id)->available,
            'sources' => $this->workspace->usage($owner->id, null, $run->id),
        ];
    }
}
