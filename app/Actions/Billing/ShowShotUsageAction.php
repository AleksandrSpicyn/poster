<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PilotShotQuote;
use App\Enums\GenerationAccountingMode;
use App\Models\Shot;
use App\Models\User;
use App\Repositories\BillingAccountingRepository;
use App\Repositories\BillingWorkspaceRepository;
use App\Tasks\Shots\PrepareShotVideoSettings;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use stdClass;

final readonly class ShowShotUsageAction
{
    public function __construct(
        private BillingAccountingRepository $accounting,
        private BillingWorkspaceRepository $workspace,
        private PrepareShotVideoSettings $settings,
    ) {}

    /** @return array{enforced: bool, estimate: ?int, available: int, sources: Collection<int, stdClass>} */
    public function run(User $owner, Shot $shot): array
    {
        $estimate = null;

        try {
            $estimate = PilotShotQuote::seconds($this->settings->run($shot)->videoSnapshot());
        } catch (ValidationException) {
            // Incompatible historical settings have no pilot estimate.
        }

        return [
            'enforced' => $this->accounting->storedMode($owner->id) === GenerationAccountingMode::ENFORCED->value,
            'estimate' => $estimate,
            'available' => $this->workspace->balance($owner->id)->available,
            'sources' => $this->workspace->usage($owner->id, $shot->id, null),
        ];
    }
}
