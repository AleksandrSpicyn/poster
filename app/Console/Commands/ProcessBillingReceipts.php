<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\SyncBillingReceiptAction;
use App\Repositories\BillingReceiptRepository;
use Illuminate\Console\Command;
use Throwable;

final class ProcessBillingReceipts extends Command
{
    protected $signature = 'billing:receipts {id?} {--retry : Повтор регистрации чека} {--external= : Сверить известный ID чека} {--offset= : UUID платежа для итогового зачёта предоплаты}';

    protected $description = 'Зарегистрировать или сверить фискальные чеки';

    public function handle(BillingReceiptRepository $receipts, SyncBillingReceiptAction $sync): int
    {
        $id = $this->argument('id');
        $external = $this->option('external');

        if ($id === null && ($external !== null || $this->option('retry'))) {
            $this->error('Укажите ID чека.');

            return self::FAILURE;
        }

        $failed = false;

        try {
            if (is_string($payment = $this->option('offset'))) {
                $receipts->queue($payment, offset: true);
            }

            foreach ($id === null ? $receipts->due() : [(int) $id] as $receipt) {
                try {
                    $sync->run($receipt, (bool) $this->option('retry'), is_string($external) ? $external : null);
                } catch (Throwable $error) {
                    report($error);
                    $this->error('Требуется сверка чека №' . $receipt);
                    $failed = true;
                }
            }
        } catch (Throwable $error) {
            report($error);
            $this->error('Не удалось подготовить чек.');

            return self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
