<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Clipping\ClippingReaders;
use App\Exceptions\ClippingProviderException;
use App\Models\ClippingConnection;
use App\Repositories\ClippingConnectionRepository;
use App\Repositories\OpusClipRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class RefreshClippingSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 120;

    public function __construct(public int $connectionId, public int $sourceId, public string $externalId) {}

    public function uniqueId(): string
    {
        return $this->connectionId . ':' . $this->sourceId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ClippingReaders $readers, ClippingConnectionRepository $connections, OpusClipRepository $clips): void
    {
        $connection = $connections->activeConnection($this->connectionId, $this->sourceId);

        if (! $connection instanceof ClippingConnection) {
            return;
        }

        try {
            $items = $readers->forConnection($connection)->clips($this->externalId);
        } catch (ClippingProviderException $error) {
            $auth = in_array($error->status, [401, 403], true);
            $connections->markSyncError($connection->id, $this->sourceId, 'HTTP ' . $error->status, $auth);

            if ($error->status === 429) {
                $this->release($error->retryAfter);

                return;
            }

            if ($auth) {
                return;
            }

            throw $error;
        } catch (RuntimeException $error) {
            $connections->markSyncError($connection->id, $this->sourceId, 'Не удалось прочитать источник.');

            throw $error;
        }

        $clips->refreshSource($connection, $this->sourceId, $this->externalId, $items);
    }
}
