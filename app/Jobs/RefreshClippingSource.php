<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Clipping\ArchivedClippingReader;
use App\Classes\Clipping\ClippingReaders;
use App\Classes\Clipping\ClippingResourceReader;
use App\Data\OpusClip\OpusSourceData;
use App\Exceptions\ClippingProviderException;
use App\Models\ClippingConnection;
use App\Models\OpusSource;
use App\Repositories\ClippingConnectionRepository;
use App\Repositories\OpusClipRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
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
        return [
            60,
            300,
            900,
        ];
    }

    public function handle(ClippingReaders $readers, ClippingConnectionRepository $connections, OpusClipRepository $clips): void
    {
        $connection = $connections->find($this->connectionId);

        if (! $connection instanceof ClippingConnection || ! $connection->import_projects) {
            return;
        }

        $source = $connections->syncSource($connection, $this->sourceId, $this->externalId);

        if (! $source instanceof OpusSource) {
            return;
        }

        $checkpoint = $source->publication_sync_checkpoint;

        try {
            $reader = $readers->forConnection($connection);

            if ($reader instanceof ArchivedClippingReader && ! $readers->findSource($reader, $connection, $this->externalId) instanceof OpusSourceData) {
                return;
            }

            if ($reader instanceof ClippingResourceReader) {
                if ($checkpoint === null) {
                    $items = $reader->clipMetadata($this->externalId);
                    $next = (string) Str::uuid();

                    if (! $clips->refreshSource($connection, $source->id, $this->externalId, $items, null, $next)) {
                        return;
                    }

                    $checkpoint = $next;
                }

                $items = $reader->clipPublications($this->externalId);
            } else {
                $items = $reader->clips($this->externalId);
            }
        } catch (ClippingProviderException $error) {
            $auth = in_array($error->status, [401, 403], true);
            $connections->markSyncError($connection, $this->sourceId, $checkpoint, 'HTTP ' . $error->status, $auth);

            if ($error->status === 429) {
                $this->release($error->retryAfter);

                return;
            }

            if ($auth) {
                return;
            }

            throw $error;
        } catch (RuntimeException $error) {
            $connections->markSyncError($connection, $this->sourceId, $checkpoint, 'Не удалось прочитать источник.');

            throw $error;
        }

        $clips->refreshSource($connection, $this->sourceId, $this->externalId, $items, $checkpoint);
    }
}
