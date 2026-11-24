<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\PilotShotQuote;
use App\Data\Billing\GenerationUsageData;
use App\Enums\GenerationAccountingMode;
use App\Models\Shot;
use App\Models\User;
use App\Repositories\BillingAccountingRepository;
use App\Repositories\BillingWorkspaceRepository;
use App\Tasks\Shots\PrepareShotVideoSettings;
use Illuminate\Validation\ValidationException;

final readonly class ShowShotUsageAction
{
    public function __construct(
        private BillingAccountingRepository $accounting,
        private BillingWorkspaceRepository $workspace,
        private PrepareShotVideoSettings $settings,
    ) {}

    public function run(User $owner, Shot $shot): GenerationUsageData
    {
        $estimate = null;

        try {
            $estimate = PilotShotQuote::seconds($this->settings->run($shot)->videoSnapshot());
        } catch (ValidationException) {
            // Incompatible historical settings have no pilot estimate.
        }

        return new GenerationUsageData(
            enforced: $this->accounting->storedMode($owner->id) === GenerationAccountingMode::ENFORCED->value,
            estimate: $estimate,
            available: $this->workspace->balance($owner->id)->available,
            sources: $this->workspace->usage($owner->id, $shot->id, null),
        );
    }
}
