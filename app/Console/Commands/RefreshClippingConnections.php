<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DiscoverClippingProjects;
use App\Jobs\RefreshClippingSource;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Console\Command;

class RefreshClippingConnections extends Command
{
    protected $signature = 'clipping:refresh';

    protected $description = 'Discover new projects and refresh clips from connected platforms';

    public function handle(ClippingConnectionRepository $connections): int
    {
        foreach ($connections->scheduledSources() as $source) {
            if ($source->clipping_connection_id !== null) {
                RefreshClippingSource::dispatch($source->clipping_connection_id, $source->id, $source->external_id);
            }
        }

        foreach ($connections->scheduledConnections() as $connection) {
            DiscoverClippingProjects::dispatch($connection->id, $connection->scope_version);
        }

        return self::SUCCESS;
    }
}
