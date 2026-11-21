<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Support\Collection;
use UnexpectedValueException;

final readonly class OpusClipClient
{
    public function __construct(private OpusClipApi $api) {}

    /** @return Generator<int, OpusSourceData> */
    public function sources(): Generator
    {
        $seen = [];
        $size = 20;

        for ($page = 0; ; $page++) {
            $projects = $this->api->rows('clip-projects', ['page' => $page, 'pageSize' => $size]);
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
        $clips = $this->api->rows('exportable-clips', $params);
        $publications = [];

        foreach ($this->api->rows('post-tasks', $params) as $post) {
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
}
