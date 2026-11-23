<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\InspectBillingPaymentAction;
use DomainException;
use Illuminate\Console\Command;

class InspectBillingPayment extends Command
{
    protected $signature = 'billing:payment-status {attempt : UUID локальной попытки}';

    protected $description = 'Read the payment from its original merchant and verify the amount and order';

    public function handle(InspectBillingPaymentAction $inspect): int
    {
        try {
            $payment = $inspect->run((string) $this->argument('attempt'));
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($payment->status->value);

        return self::SUCCESS;
    }
}
