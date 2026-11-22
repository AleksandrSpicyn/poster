<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Models\User;
use App\Repositories\ClippingConnectionRepository;

final readonly class GetClippingConnectionsAction
{
    public function __construct(private ClippingConnectionRepository $connections) {}

    /** @return array<string, mixed> */
    public function run(User $owner): array
    {
        return ['connections' => $this->connections->owned($owner)];
    }

    /** @return array<string, mixed> */
    public function history(User $owner): array
    {
        return ['imports' => $this->connections->history($owner), 'sources' => $this->connections->recentSources($owner)];
    }
}
