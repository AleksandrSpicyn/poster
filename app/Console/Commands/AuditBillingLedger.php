<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\BillingAuditRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class AuditBillingLedger extends Command
{
    protected $signature = 'billing:audit';

    protected $description = 'Сверить финансовый журнал и показать незавершённые операции';

    public function handle(BillingAuditRepository $audit): int
    {
        $counts = $audit->counts();

        foreach ($counts as $label => $count) {
            $this->line($label . ': ' . $count);
        }

        if (array_sum($counts) > 0) {
            Log::warning('Операции биллинга требуют сверки.', $counts);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
