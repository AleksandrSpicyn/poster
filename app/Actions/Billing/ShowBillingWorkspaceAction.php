<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Classes\Billing\CheckoutAvailability;
use App\Classes\Billing\FiscalProfile;
use App\Enums\BillingProduct;
use App\Enums\BillingSection;
use App\Repositories\BillingWorkspaceRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;

final readonly class ShowBillingWorkspaceAction
{
    public function __construct(private BillingWorkspaceRepository $workspace, private CheckoutAvailability $availability, private GetBillingCatalogAction $catalog) {}

    /** @return array<string, mixed> */
    public function run(int $owner, BillingSection $section, int $page = 1, ?BillingProduct $selected = null, ?int $orderId = null, ?int $project = null): array
    {
        $account = fn(): ?stdClass => once(fn(): ?stdClass => $this->workspace->account($owner));
        $order = $orderId === null ? null : $this->workspace->order($owner, $orderId);
        $history = fn(): ?array => match ($section) {
            BillingSection::PAYMENTS => $orderId === null ? $this->workspace->paymentHistory($owner, $page) : null,
            BillingSection::SECONDS => $this->workspace->secondsHistory($owner, $page, $project),
            default => null,
        };

        return [
            'section' => $section->value,
            'catalogVersion' => BillingProduct::CATALOG_VERSION,
            'catalog' => $this->catalog->run(...),
            'account' => $account,
            'sources' => fn(): Collection|array => $section === BillingSection::OVERVIEW ? $this->workspace->sources($owner) : [],
            'history' => $history,
            'projectFilter' => $section === BillingSection::SECONDS ? $project : null,
            'historyProjects' => fn(): Collection|array => $section === BillingSection::SECONDS ? $this->workspace->historyProjects($owner) : [],
            'selected' => $selected?->value,
            'checkout' => fn(): ?array => $selected instanceof BillingProduct ? [
                'request_key' => (string) Str::uuid(),
                'catalog_version' => BillingProduct::CATALOG_VERSION,
                'offer_url' => FiscalProfile::offerUrl(),
                'test_mode' => $this->availability->testMode(),
                'can_auto_renew' => $selected->isMonthly() && (bool) ($account()->automatic_payments_enabled ?? false),
                'blocked' => $this->availability->blocked($owner, $selected, (bool) ($account()->sales_enabled ?? false)),
            ] : null,
            'pendingOrder' => fn(): ?int => $this->workspace->pendingOrder($owner),
            'order' => $order,
            'confirmationUrl' => fn(): ?string => $orderId === null ? null : $this->workspace->confirmationUrl($orderId),
            'refunds' => fn(): Collection|array => $orderId === null ? [] : $this->workspace->refunds($orderId),
            'receipts' => fn(): Collection|array => $orderId === null ? [] : $this->workspace->receipts($orderId),
        ];
    }
}
