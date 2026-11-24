<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\SyncBillingCheckoutAction;
use App\Repositories\BillingCheckoutRepository;
use Illuminate\Console\Command;

final class SyncBillingCheckouts extends Command
{
    protected $signature = 'billing:sync-checkouts';

    protected $description = 'Сверить ожидающие платежи кабинета с исходными магазинами';

    public function handle(BillingCheckoutRepository $checkout, SyncBillingCheckoutAction $sync): void
    {
        foreach ($checkout->due() as $id) {
            $sync->run($id);
        }
    }
}
