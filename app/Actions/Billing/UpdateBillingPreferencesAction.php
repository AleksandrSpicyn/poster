<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\NextBillingPlanData;
use App\Data\Billing\RenewalConsentData;
use App\Models\User;
use App\Repositories\BillingWorkspaceRepository;

final readonly class UpdateBillingPreferencesAction
{
    public function __construct(private BillingWorkspaceRepository $workspace) {}

    public function nextPlan(User $owner, NextBillingPlanData $data): void
    {
        $this->workspace->setNextPlan($owner->id, $data);
    }

    public function renewalConsent(User $owner, RenewalConsentData $data): void
    {
        $this->workspace->setConsent($owner->id, $data);
    }

    public function detachCard(User $owner): void
    {
        $this->workspace->detachCard($owner->id);
    }
}
