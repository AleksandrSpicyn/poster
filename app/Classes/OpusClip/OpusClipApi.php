<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final readonly class OpusClipApi
{
    public function __construct(#[Config('services.opus_clip.key')] private string $key) {}

    /**
     * @param array<string, int|string> $query
     * @return list<array<array-key, mixed>>
     */
    public function rows(string $endpoint, array $query): array
    {
        if (trim($this->key) === '') {
            throw new RuntimeException('OPUS_CLIP_API_KEY is missing.');
        }

        try {
            $response = Http::withToken($this->key)->acceptJson()->withoutRedirecting()->timeout(30)
                ->retry(2, static function (int $attempt, mixed $error): int {
                    $seconds = $error instanceof RequestException ? $error->response->header('Retry-After') : '';

                    return (is_numeric($seconds) ? min(60, max(1, (int) $seconds)) : 60) * 1000;
                }, static fn(Throwable $error): bool => $error instanceof RequestException && $error->response->status() === 429, throw: false)
                ->get('https://api.opus.pro/api/' . $endpoint, $query);
        } catch (ConnectionException) {
            throw new RuntimeException("Cannot reach api.opus.pro ($endpoint).");
        }

        if (! $response->successful()) {
            throw new RuntimeException("OpusClip $endpoint returned HTTP " . $response->status() . '.');
        }

        $rows = match ($endpoint) {
            'post-tasks' => $response->json('data.data'),
            'clip-projects' => $response->json('list', $response->json('data.list', $response->json('data', $response->json()))),
            default => $response->json('data', $response->json()),
        };

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new UnexpectedValueException("Unexpected OpusClip $endpoint response.");
        }

        return array_map(static function (mixed $row) use ($endpoint): array {
            if (! is_array($row)) {
                throw new UnexpectedValueException("Invalid row in OpusClip $endpoint.");
            }

            return $row;
        }, $rows);
    }
}
