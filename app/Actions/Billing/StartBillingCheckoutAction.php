<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\CheckoutData;
use App\Enums\BillingProduct;
use App\Models\User;
use App\Repositories\BillingCheckoutRepository;

final readonly class StartBillingCheckoutAction
{
    public function __construct(private BillingCheckoutRepository $checkout, private SyncBillingCheckoutAction $sync) {}

    public function run(User $owner, BillingProduct $product, CheckoutData $data): int
    {
        $order = $this->checkout->open($owner->id, $product, $data);
        $id = $this->checkout->attemptForOrder($order);

        if ($id !== null) {
            $this->sync->run($id);
        }

        return $order;
    }
}
