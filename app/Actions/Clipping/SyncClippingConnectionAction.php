<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Jobs\DiscoverClippingProjects;
use App\Models\ClippingConnection;

final readonly class SyncClippingConnectionAction
{
    public function run(ClippingConnection $connection): void
    {
        if ($connection->import_projects) {
            DiscoverClippingProjects::dispatch($connection->id, $connection->scope_version, true)->afterCommit();
        }
    }
}
