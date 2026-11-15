<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Episodes\ReconcileEpisodeScriptsAction;
use Illuminate\Console\Command;

class ReconcileEpisodeScripts extends Command
{
    protected $signature = 'app:reconcile-episode-scripts {--limit=50 : Maximum number of pending requests}';

    protected $description = 'Resume safe pending work without repeating unconfirmed external submissions';

    public function handle(ReconcileEpisodeScriptsAction $action): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 100) {
            $this->error('The limit must be between 1 and 100.');

            return self::FAILURE;
        }

        $this->info('Queued requests: ' . $action->run($limit));

        return self::SUCCESS;
    }
}
