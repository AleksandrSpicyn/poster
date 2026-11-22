<?php

declare(strict_types=1);

namespace App\Classes\Clipping;

use App\Models\ClippingConnection;
use Illuminate\Container\Attributes\Config;
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
