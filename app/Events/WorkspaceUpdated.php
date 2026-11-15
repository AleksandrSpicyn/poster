<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class WorkspaceUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(private readonly bool $seriesOnly = false) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->seriesOnly ? 'workspace.series' : 'workspace')];
    }

    public function broadcastAs(): string
    {
        return 'workspace.updated';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWhen(): bool
    {
        return filled(config('broadcasting.default')) && config('broadcasting.default') !== 'null';
    }

    /** @return array{changed: bool} */
    public function broadcastWith(): array
    {
        return ['changed' => true];
    }
}
