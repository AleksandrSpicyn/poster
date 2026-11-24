<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GenerationAccountingMode;
use App\Repositories\GenerationReservationRepository;
use DomainException;
use Illuminate\Console\Command;

final class ConfigureBillingPilot extends Command
{
    protected $signature = 'billing:pilot {owner} {--mode= : legacy, shadow или enforced} {--sales= : on или off} {--automatic= : on или off}';

    protected $description = 'Настроить продажи, автоплатежи и учёт секунд владельца';

    public function handle(GenerationReservationRepository $usage): int
    {
        $mode = $this->option('mode');
        $owner = (int) $this->argument('owner');

        try {
            $accounting = is_string($mode) ? GenerationAccountingMode::tryFrom($mode) : null;

            if ($mode !== null && $accounting === null) {
                throw new DomainException('Неизвестный режим учёта.');
            }

            $usage->configurePilot($owner, $accounting, $this->flag('sales'), $this->flag('automatic'));
            $this->line('Режим учёта: ' . $usage->mode($owner));
        } catch (DomainException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function flag(string $name): ?bool
    {
        return match ($this->option($name)) {
            null => null,
            'on' => true,
            'off' => false,
            default => throw new DomainException('Для параметра ' . $name . ' укажите on или off.'),
        };
    }
}
