<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final readonly class OpusClipClient
{
    public function __construct(#[Config('services.opus_clip.key')] private string $key) {}

    /** @return Generator<int, OpusSourceData> */
    public function sources(): Generator
    {
        $seen = [];
        $size = 20;

        for ($page = 0; ; $page++) {
            $projects = $this->rows('clip-projects', ['page' => $page, 'pageSize' => $size]);
            $previousCount = count($seen);

            foreach ($projects as $project) {
                $id = Payload::string($project['projectId'] ?? null, 'projectId');

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;

                if (! ($project['isDeleted'] ?? false)) {
                    yield new OpusSourceData($id, is_string($project['title'] ?? null) ? $project['title'] : null);
                }
            }

            if (count($projects) < $size) {
                break;
            }

            if (count($seen) === $previousCount) {
                throw new UnexpectedValueException('OpusClip repeated a project page.');
            }
        }
    }

    /** @return Collection<int, ImportedClipData> */
    public function clips(string $projectId): Collection
    {
        $params = ['q' => 'findByProjectId', 'projectId' => $projectId];
        $clips = $this->rows('exportable-clips', $params);
        $publications = [];

        foreach ($this->rows('post-tasks', $params) as $post) {
            if (($post['projectId'] ?? $projectId) !== $projectId) {
                continue;
            }

            $id = $this->clipId($post['clipId'] ?? null, $projectId);
            $url = $post['extVideoLink'] ?? null;
            $publications[$id][] = [
                'platform' => strtolower(Payload::string($post['platform'] ?? null, 'platform')),
                'status' => is_string($post['status'] ?? null) ? $post['status'] : null,
                'url' => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null,
                'publish_at' => is_string($post['publishAt'] ?? null) ? $post['publishAt'] : null,
            ];
        }

        $items = collect($clips)->keyBy(fn(array $clip): string => $this->clipId($clip['id'] ?? null, $projectId));

        return $items->keys()->merge(array_keys($publications))->unique()->values()
            ->map(function ($id) use ($items, $publications): ImportedClipData {
                $clip = $items->get($id, []);

                return new ImportedClipData(
                    (string) $id,
                    is_string($clip['title'] ?? null) ? $clip['title'] : null,
                    is_numeric($clip['durationMs'] ?? null) ? (int) $clip['durationMs'] : null,
                    is_numeric($clip['score'] ?? null) ? (float) $clip['score'] : null,
                    $publications[$id] ?? [],
                );
            });
    }

    private function clipId(mixed $value, string $projectId): string
    {
        $id = Payload::string($value, 'clipId');

        if (str_contains($id, '.') && ! str_starts_with($id, $projectId . '.')) {
            throw new UnexpectedValueException('OpusClip returned a clip from another project.');
        }

        return Payload::string(str_starts_with($id, $projectId . '.') ? substr($id, strlen($projectId) + 1) : $id, 'clipId');
    }

    /**
     * @param array<string, int|string> $query
     * @return list<array<array-key, mixed>>
     */
    private function rows(string $endpoint, array $query): array
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
