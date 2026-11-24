<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\SyncBillingCheckoutAction;
use App\Actions\Billing\SyncBillingRefundAction;
use App\Repositories\BillingCheckoutRepository;
use App\Repositories\BillingRefundRepository;
use Illuminate\Console\Command;
use Throwable;

final class SyncBillingCheckouts extends Command
{
    protected $signature = 'billing:sync-checkouts';

    protected $description = 'Сверить ожидающие платежи кабинета с исходными магазинами';

    public function handle(BillingCheckoutRepository $checkout, SyncBillingCheckoutAction $sync, BillingRefundRepository $refunds, SyncBillingRefundAction $syncRefund): void
    {
        foreach ($checkout->due() as $id) {
            $sync->run($id);
        }

        foreach ($refunds->due() as $id) {
            try {
                $syncRefund->run($id);
            } catch (Throwable $error) {
                report($error);
            }
        }
    }
}
