<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\CheckoutAvailability;
use App\Enums\BillingProduct;
use App\Enums\BillingSection;
use App\Repositories\BillingWorkspaceRepository;
use Illuminate\Support\Str;
use stdClass;

final readonly class ShowBillingWorkspaceAction
{
    public function __construct(private BillingWorkspaceRepository $workspace, private CheckoutAvailability $availability) {}

    /** @return array<string, mixed> */
    public function run(int $owner, BillingSection $section, int $page = 1, ?BillingProduct $selected = null, ?int $orderId = null, ?int $project = null): array
    {
        $account = $this->workspace->account($owner);
        $order = $orderId === null ? null : $this->workspace->order($owner, $orderId);
        $history = match ($section) {
            BillingSection::PAYMENTS, BillingSection::SECONDS => $order instanceof stdClass ? null : $this->workspace->history($owner, $section === BillingSection::PAYMENTS, $page, $project),
            default => null,
        };

        return [
            'section' => $section->value,
            'catalog' => collect(BillingProduct::cases())->map(fn(BillingProduct $product): array => [
                'code' => $product->value, 'label' => $product->label(), 'seconds' => $product->seconds(),
                'amount' => $product->amountKopecks(), 'monthly' => $product->isMonthly(),
            ]),
            'account' => $account,
            'balance' => $section === BillingSection::OVERVIEW ? $this->workspace->balance($owner) : null,
            'sources' => $section === BillingSection::OVERVIEW ? $this->workspace->sources($owner) : [],
            'history' => $history,
            'projectFilter' => $section === BillingSection::SECONDS ? $project : null,
            'historyProjects' => $section === BillingSection::SECONDS ? $this->workspace->historyProjects($owner) : [],
            'selected' => $selected?->value,
            'checkout' => $selected instanceof BillingProduct ? [
                'request_key' => (string) Str::uuid(), 'catalog_version' => BillingProduct::CATALOG_VERSION,
                'blocked' => $this->availability->blocked($owner, $selected, (bool) ($account->sales_enabled ?? false)),
            ] : null,
            'pendingOrder' => $this->workspace->pendingOrder($owner),
            'order' => $order,
            'confirmationUrl' => $orderId === null ? null : $this->workspace->confirmationUrl($orderId),
            'refunds' => $orderId === null ? [] : $this->workspace->refunds($orderId),
            'receipts' => $orderId === null ? [] : $this->workspace->receipts($orderId),
        ];
    }
}
