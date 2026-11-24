<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Exceptions\ClippingProviderException;
use Carbon\Carbon;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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
                ->get(config()->string('services.opus_clip.api_url') . $endpoint, $query);
        } catch (ConnectionException) {
            throw new RuntimeException("Cannot reach api.opus.pro ($endpoint).");
        }

        if (! $response->successful()) {
            $header = $response->header('Retry-After');
            $delay = is_numeric($header) ? (int) $header : ((Carbon::parse($header)->getTimestamp() ?: Carbon::now()->getTimestamp() + 60) - Carbon::now()->getTimestamp());

            throw new ClippingProviderException($endpoint, $response->status(), max(1, $delay));
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
