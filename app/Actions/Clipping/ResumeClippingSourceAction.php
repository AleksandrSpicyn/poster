<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Jobs\RefreshClippingSource;
use App\Models\ClippingConnection;
use App\Models\OpusSource;
use App\Repositories\ClippingConnectionRepository;

final readonly class ResumeClippingSourceAction
{
    public function __construct(private ClippingConnectionRepository $connections) {}

    public function run(ClippingConnection $connection, int $sourceId): void
    {
        $source = $this->connections->resume($connection, $sourceId);

        if ($source instanceof OpusSource) {
            RefreshClippingSource::dispatch($connection->id, $source->id, $source->external_id)->afterCommit();
        }
    }
}
