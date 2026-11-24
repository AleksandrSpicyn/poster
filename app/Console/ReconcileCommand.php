<?php

declare(strict_types=1);

namespace App\Console;

use Closure;
use Illuminate\Console\Command;

abstract class ReconcileCommand extends Command
{
    /** @param Closure(int): int $reconcile */
    protected function runBatch(Closure $reconcile, string $label): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 100) {
            $this->error('The limit must be between 1 and 100.');

            return self::FAILURE;
        }

        $this->info($label . ': ' . $reconcile($limit));

        return self::SUCCESS;
    }
}
