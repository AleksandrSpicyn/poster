<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\ModelId;
use App\Repositories\BillingOperatorRepository;
use App\Repositories\GenerationReservationRepository;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ResolveBillingHold extends Command
{
    protected $signature = 'billing:release-hold {prompt} {--operator=} {--reason= : Подтверждённая причина невозможности выдачи файла}';

    protected $description = 'Освободить резерв остановленной работы после проверки доставки';

    public function handle(GenerationReservationRepository $usage, BillingOperatorRepository $operators): int
    {
        try {
            $operator = ModelId::parse($this->option('operator'));
            $operators->authorize($operator);
            $usage->releaseUndeliverable(ModelId::parse($this->argument('prompt')), $operator, trim((string) $this->option('reason')));
        } catch (DomainException|ValidationException $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
