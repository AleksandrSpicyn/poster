<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Repositories\BillingWorkspaceRepository;

final readonly class DetachPaymentMethodAction
{
    public function __construct(private BillingWorkspaceRepository $workspace) {}

    public function run(int $ownerId): void
    {
        $this->workspace->detachCard($ownerId);
    }
}
