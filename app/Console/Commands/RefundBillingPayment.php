<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\SyncBillingRefundAction;
use App\Repositories\BillingRefundRepository;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

final class RefundBillingPayment extends Command
{
    protected $signature = 'billing:refund {id : Постоянный UUID возврата} {--payment=} {--operator=} {--kopecks=} {--seconds=} {--reason=} {--external= : Сверить отправленную попытку по ID возврата в магазине}';

    protected $description = 'Оформить согласованный возврат либо сверить его исход';

    public function handle(BillingRefundRepository $refunds, SyncBillingRefundAction $sync): int
    {
        $id = (string) $this->argument('id');
        $external = $this->option('external');

        try {
            if ($external === null) {
                $amount = filter_var($this->option('kopecks'), FILTER_VALIDATE_INT);
                $seconds = filter_var($this->option('seconds'), FILTER_VALIDATE_INT);

                if (! is_int($amount) || ! is_int($seconds)) {
                    throw new DomainException('Укажите целые копейки и количество корректируемых секунд.');
                }

                $refunds->open($id, (string) $this->option('payment'), (int) $this->option('operator'), $amount, $seconds, (string) $this->option('reason'));
            }

            $status = $sync->run($id, is_string($external) ? $external : null);
            $this->line($status->value ?? 'Нет новой отправки. Проверьте историю возврата.');
        } catch (DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } catch (Throwable $error) {
            report($error);
            $this->error('Исход требует сверки по исходному магазину.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
