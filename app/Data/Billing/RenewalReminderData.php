<?php

declare(strict_types=1);

namespace App\Data\Billing;

use App\Enums\BillingProduct;
use App\Models\User;

final readonly class RenewalReminderData
{
    public function __construct(public User $recipient, public string $due, public BillingProduct $product, public bool $automatic) {}
}
