<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\RenewalConsentData;
use App\Repositories\BillingWorkspaceRepository;

final readonly class UpdateRenewalConsentAction
{
    public function __construct(private BillingWorkspaceRepository $workspace) {}

    public function run(int $ownerId, RenewalConsentData $data): void
    {
        $this->workspace->setConsent($ownerId, $data);
    }
}
