<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Clipping\ClippingReaders;
use App\Exceptions\ClippingProviderException;
use App\Models\ClippingConnection;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DiscoverClippingProjects implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 120;

    public function __construct(public int $connectionId, public int $version, public bool $refreshExisting = false) {}

    public function uniqueId(): string
    {
        return implode(':', [
            $this->connectionId,
            $this->version,
            (int) $this->refreshExisting,
        ]);
    }

    public function handle(ClippingReaders $readers, ClippingConnectionRepository $connections): void
    {
        $connection = $connections->find($this->connectionId);

        if (! $connection instanceof ClippingConnection || ! $connection->import_projects || $connection->scope_version !== $this->version) {
            return;
        }

        try {
            $reader = $readers->forConnection($connection);

            $known = $connections->catalogSources($connection);

            foreach ($readers->sources($reader, $connection) as $candidate) {
                $existing = $known->get($candidate->id);

                if ($existing !== null && ! $this->refreshExisting) {
                    continue;
                }

                $source = $existing ?? $connections->discover($connection, $candidate);

                if ($source !== null && $source->getAttribute('sync_paused_at') === null) {
                    RefreshClippingSource::dispatch($connection->id, $source->id, $source->external_id);
                }
            }
        } catch (ClippingProviderException $error) {
            $denied = in_array($error->status, [401, 403], true);
            $connections->recordDiscovery($connection, $denied ? 'Площадка отклонила ключ. Обновите его в настройках источника.' : 'Площадка временно недоступна. Импорт будет повторён.');

            if ($error->status === 429) {
                $this->release($error->retryAfter);
            } elseif (! $denied) {
                throw $error;
            }

            return;
        } catch (ValidationException|RuntimeException $error) {
            $connections->recordDiscovery($connection, 'Не удалось получить проекты. Проверьте подключение и повторите импорт.');

            throw $error;
        }

        $connections->recordDiscovery($connection, null);
    }
}
