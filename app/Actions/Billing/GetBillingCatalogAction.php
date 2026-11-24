<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\BillingProduct;
use Illuminate\Support\Collection;

final readonly class GetBillingCatalogAction
{
    /** @return Collection<int, array{code: string, label: string, seconds: int, amount: int, monthly: bool}> */
    public function run(): Collection
    {
        return collect(BillingProduct::cases())->map(fn(BillingProduct $product): array => [
            'code' => $product->value,
            'label' => $product->label(),
            'seconds' => $product->seconds(),
            'amount' => $product->amountKopecks(),
            'monthly' => $product->isMonthly(),
        ]);
    }
}
