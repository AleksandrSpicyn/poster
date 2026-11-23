<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VideoProvider;
use App\Repositories\GenerationProviderUsageRepository;
use DomainException;
use Illuminate\Console\Command;

class ReconcileGenerationProviderCharge extends Command
{
    protected $signature = 'billing:provider-charge {provider} {external_id} {amount} {currency} {reference}';

    protected $description = 'Record a verified per-attempt charge from the provider statement';

    public function handle(GenerationProviderUsageRepository $usage): int
    {
        $provider = VideoProvider::tryFrom((string) $this->argument('provider'));
        $amount = (string) $this->argument('amount');
        $currency = (string) $this->argument('currency');
        $reference = trim((string) $this->argument('reference'));

        if ($provider === null || preg_match('/\A(\d{1,10})(?:\.(\d{1,8}))?\z/', $amount, $parts) !== 1
            || preg_match('/\A[A-Z]{3}\z/', $currency) !== 1 || $reference === '' || mb_strlen($reference) > 255) {
            $this->error('Проверьте провайдера, сумму, валюту и идентификатор строки детализации.');

            return self::INVALID;
        }

        $charge = (int) ($parts[1] . str_pad($parts[2] ?? '', 8, '0'));

        try {
            $usage->reconcile($provider, (string) $this->argument('external_id'), $charge, $currency, $reference);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Начисление сверено.');

        return self::SUCCESS;
    }
}
