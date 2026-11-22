<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Classes\Clipping\ClippingResourceReader;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Support\Collection;
use UnexpectedValueException;

final readonly class OpusClipClient implements ClippingResourceReader
{
    public function __construct(private OpusClipApi $api) {}

    public static function withKey(string $key): self
    {
        return new self(new OpusClipApi($key));
    }

    public static function readsAccounts(): bool
    {
        return true;
    }

    public function accounts(): array
    {
        return array_map(static fn(array $row): array => [
            'id' => Payload::string($row['postAccountId'] ?? null, 'postAccountId'),
            'platform' => match ($platform = Payload::string($row['platform'] ?? null, 'platform')) {
                'YOUTUBE' => 'youtube', 'TIKTOK_BUSINESS' => 'tiktok', 'INSTAGRAM_BUSINESS' => 'instagram',
                default => strtolower($platform),
            },
            'external_id' => Payload::string($row['extUserId'] ?? null, 'extUserId'),
            'name' => is_string($row['extUserName'] ?? null) ? $row['extUserName'] : '',
        ], $this->api->rows('social-accounts', ['q' => 'mine']));
    }

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
        $metadata = $this->clipMetadata($projectId)->keyBy('id');
        $published = $this->clipPublications($projectId)->keyBy('id');

        return $metadata->keys()->merge($published->keys())->unique()->values()->map(function ($id) use ($metadata, $published): ImportedClipData {
            $clip = $metadata->get($id);

            return new ImportedClipData((string) $id, $clip?->title, $clip?->durationMs, $clip?->score, $published->get($id)->publications ?? []);
        });
    }

    public function clipMetadata(string $projectId): Collection
    {
        $rows = $this->api->rows('exportable-clips', ['q' => 'findByProjectId', 'projectId' => $projectId]);

        return collect($rows)->map(fn(array $clip): ImportedClipData => new ImportedClipData(
            $this->clipId($clip['id'] ?? null, $projectId),
            is_string($clip['title'] ?? null) ? $clip['title'] : null,
            is_numeric($clip['durationMs'] ?? null) ? (int) $clip['durationMs'] : null,
            is_numeric($clip['score'] ?? null) ? (float) $clip['score'] : null,
            [],
        ))->keyBy('id')->values();
    }

    public function clipPublications(string $projectId): Collection
    {
        $params = ['q' => 'findByProjectId', 'projectId' => $projectId];
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

        return collect($publications)->map(static fn(array $posts, int|string $id): ImportedClipData => new ImportedClipData((string) $id, null, null, null, $posts))->values();
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
