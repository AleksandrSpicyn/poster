<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Data\Billing\RenewalReminderData;
use App\Notifications\BillingRenewalReminder;
use App\Repositories\BillingRenewalRepository;
use Throwable;

final readonly class DeliverBillingRemindersAction
{
    public function __construct(private BillingRenewalRepository $renewals) {}

    public function run(): void
    {
        foreach ($this->renewals->pendingReminderIds() as $id) {
            $reminder = $this->renewals->claimReminder($id);

            if (! $reminder instanceof RenewalReminderData) {
                continue;
            }

            try {
                $reminder->recipient->notifyNow(new BillingRenewalReminder($reminder->due, $reminder->product, $reminder->automatic));
            } catch (Throwable $error) {
                report($error);
                $this->renewals->markReminderUnconfirmed($id);

                continue;
            }

            $this->renewals->markReminderDelivered($id);
        }
    }
}
