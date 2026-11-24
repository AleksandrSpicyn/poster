<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GenerationProviderUsage;
use App\Repositories\GenerationProviderUsageRepository;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

class ReportGenerationProviderUsage extends Command
{
    protected $signature = 'billing:provider-usage {date : YYYY-MM-DD} {--timezone=UTC : Часовой пояс детализации}';

    protected $description = 'Show provider receipts and confirmed charges for the selected local day';

    public function handle(GenerationProviderUsageRepository $usage): int
    {
        $zone = (string) $this->option('timezone');
        $date = (string) $this->argument('date');

        if (! in_array($zone, DateTimeZone::listIdentifiers(), true) || ! CarbonImmutable::canBeCreatedFromFormat($date, '!Y-m-d')) {
            $this->error('Проверьте дату и часовой пояс.');

            return self::INVALID;
        }

        $from = CarbonImmutable::createFromFormat('!Y-m-d', $date, $zone);

        if (! $from instanceof CarbonImmutable || $from->format('Y-m-d') !== $date) {
            $this->error('Дата не существует.');

            return self::INVALID;
        }

        $rows = $usage->observedBetween($from, $from->addDay())->map(static function (GenerationProviderUsage $row): array {
            $charge = $row->charge_e8 === null ? '—' : sprintf('%d.%08d %s', intdiv($row->charge_e8, 100_000_000), $row->charge_e8 % 100_000_000, $row->currency);

            return [
                $row->provider,
                $row->external_id,
                $row->result_model ?? $row->requested_model ?? '—',
                $row->input_mode ?? '—',
                $row->resolution ?? '—',
                $row->status,
                $row->duration ?? '—',
                $row->completion_tokens ?? '—',
                $charge,
                $row->finance_reference ?? '—',
            ];
        });
        $this->table([
            'Провайдер',
            'Попытка',
            'Модель',
            'Режим',
            'Разрешение',
            'Статус',
            'Секунды',
            'Токены',
            'Начисление',
            'Детализация',
        ], $rows);

        return self::SUCCESS;
    }
}
