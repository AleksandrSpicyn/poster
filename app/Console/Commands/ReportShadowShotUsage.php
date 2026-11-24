<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\BillingAccountingRepository;
use DomainException;
use Illuminate\Console\Command;
use stdClass;

final class ReportShadowShotUsage extends Command
{
    protected $signature = 'billing:shadow-usage {owner : ID владельца} {--enable : Наблюдать новые дубли} {--disable : Завершить наблюдение за новыми дублями}';

    protected $description = 'Показать расчётные секунды дублей по владельцу';

    public function handle(BillingAccountingRepository $accounting): int
    {
        $owner = filter_var($this->argument('owner'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (! is_int($owner) || ($this->option('enable') && $this->option('disable'))) {
            $this->error('Укажите ID владельца и не более одного режима.');

            return self::FAILURE;
        }

        if ($this->option('enable') || $this->option('disable')) {
            try {
                $accounting->configureShadowUsage($owner, (bool) $this->option('enable'));
            } catch (DomainException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $mode = $accounting->storedMode($owner);

        if ($mode === null) {
            $this->error('Владелец не найден.');

            return self::FAILURE;
        }

        $this->info('Режим учёта: ' . $mode);
        $rows = $accounting->shadowTotals($owner);

        if ($rows->isEmpty()) {
            $this->info('Наблюдавшихся дублей пока нет.');
        } else {
            $this->table([
                'Состояние',
                'Дубли',
                'Расчётные секунды',
            ], $rows->map(fn(stdClass $row): array => [
                $row->status,
                $row->takes,
                $row->seconds,
            ])->all());
        }

        return self::SUCCESS;
    }
}
