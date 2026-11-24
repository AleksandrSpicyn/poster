<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Billing\ReconcileBillingPaymentAction;
use App\Enums\PaymentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class RecoverBillingPayment extends Command
{
    protected $signature = 'billing:recover-payment {attempt : UUID локальной попытки} {external : ID платежа в исходном магазине}';

    protected $description = 'Сверить отправленный тестовый платёж по ID из кабинета магазина и провести подтверждённый результат';

    public function handle(ReconcileBillingPaymentAction $action): int
    {
        $id = (string) $this->argument('attempt');
        $external = trim((string) $this->argument('external'));

        if (! Str::isUuid($id) || $external === '' || strlen($external) > 128) {
            $this->error('Проверьте UUID попытки и идентификатор платежа.');

            return self::FAILURE;
        }

        try {
            $status = $action->run($id, $external);
        } catch (Throwable $error) {
            report($error);
            $this->error('Сверка не завершена. Проверьте данные платежа и доступность исходного магазина.');

            return self::FAILURE;
        }

        if (! $status instanceof PaymentStatus) {
            $this->error('Нет открытой отправленной попытки с такими реквизитами.');

            return self::FAILURE;
        }

        $this->line($status->value);

        return self::SUCCESS;
    }
}
