<?php

declare(strict_types=1);

namespace App\Classes\Clipping;

use App\Data\OpusClip\OpusSourceData;
use App\Models\ClippingConnection;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final readonly class ClippingReaders
{
    /** @param array<string, array{label: string, reader: class-string<ClippingReader>}> $drivers */
    public function __construct(
        #[Config('clipping.drivers')] private array $drivers,
        #[Config('services.opus_clip.key')] private string $serverKey,
    ) {}

    /** @return array<string, string> */
    public function labels(): array
    {
        return array_map(static fn(array $driver): string => $driver['label'], $this->drivers);
    }

    public function supportsArchives(string $driver): bool
    {
        return is_a($this->drivers[$driver]['reader'] ?? '', ArchivedClippingReader::class, true);
    }

    public function supportsAccounts(string $driver): bool
    {
        $reader = $this->drivers[$driver]['reader'] ?? null;

        return $reader !== null && $reader::readsAccounts();
    }

    /** @return iterable<int, OpusSourceData> */
    public function sources(ClippingReader $reader, ClippingConnection $connection): iterable
    {
        $sources = $connection->import_archives && $reader instanceof ArchivedClippingReader ? $reader->sourcesIncludingArchives() : $reader->sources();

        foreach ($sources as $source) {
            yield $source;
        }
    }

    public function findSource(ClippingReader $reader, ClippingConnection $connection, string $externalId): ?OpusSourceData
    {
        return Arr::first($this->sources($reader, $connection), static fn(OpusSourceData $source): bool => $source->id === $externalId);
    }

    public function forConnection(ClippingConnection $connection): ClippingReader
    {
        $driver = $this->drivers[$connection->driver] ?? null;
        $key = $connection->uses_server_key ? $this->serverKey : $connection->api_key;

        if ($driver === null || blank($key)) {
            throw ValidationException::withMessages(['connection' => 'Проверьте драйвер и ключ подключения.']);
        }

        return $driver['reader']::withKey((string) $key);
    }
}
