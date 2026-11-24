<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\DeliverBillingRemindersAction;
use App\Repositories\BillingRenewalRepository;
use App\Repositories\GenerationCreditRepository;
use Illuminate\Console\Command;

final class ProcessBillingPeriods extends Command
{
    protected $signature = 'billing:process-periods';

    protected $description = 'Закрыть истёкшие секунды, подготовить напоминания и продления';

    public function handle(GenerationCreditRepository $credits, BillingRenewalRepository $renewals, DeliverBillingRemindersAction $reminders): void
    {
        $credits->expire();
        $renewals->process();
        $reminders->run();
    }
}
