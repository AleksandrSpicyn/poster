<?php

declare(strict_types=1);

namespace App\Events;

use App\Support\WorkspaceOwner;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class WorkspaceUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public int $tries = 1;

    public int $timeout = 15;

    public function __construct(private readonly int $ownerId, private readonly string $resource, private readonly int $resourceId, private readonly int $version) {}

    public static function forResource(Model $model): void
    {
        $ownerId = WorkspaceOwner::id($model);

        $id = $model->getKey();

        if ($ownerId !== null && is_int($id)) {
            self::dispatch($ownerId, class_basename($model), $id, (int) now()->format('Uu'));
        }
    }

    public static function forBalance(int $owner): void
    {
        self::dispatch($owner, 'GenerationBalance', $owner, (int) now()->format('Uu'));
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspace.' . $this->ownerId)];
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

    /** @return array{resource: string, id: int, version: int} */
    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resource,
            'id' => $this->resourceId,
            'version' => $this->version,
        ];
    }
}
