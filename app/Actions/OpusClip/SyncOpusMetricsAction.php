<?php

declare(strict_types=1);

namespace App\Actions\OpusClip;

use App\Classes\OpusClip\ChannelMetricsClient;
use App\Classes\OpusClip\VideoReference;
use App\Enums\ChannelType;
use App\Events\WorkspaceUpdated;
use App\Models\Channel;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use App\Repositories\OpusMetricRepository;

final readonly class SyncOpusMetricsAction
{
    public function __construct(private ChannelMetricsClient $client, private OpusClipRepository $clips, private OpusMetricRepository $metrics) {}

    /**
     * @param list<ChannelType> $platforms
     * @return array<string, array<array-key, array<int, int>>>
     */
    public function targets(Project $project, array $platforms): array
    {
        $targets = [];

        foreach ($this->clips->forMetrics($project) as $clip) {
            foreach ($clip->publications as $post) {
                if ($post['url'] === null) {
                    continue;
                }

                foreach ($platforms as $platform) {
                    $id = VideoReference::fromUrl($platform, $post['url']);

                    if ($id !== null) {
                        $targets[$platform->value][$id][$clip->id] = $clip->id;

                        break;
                    }
                }
            }
        }

        return $targets;
    }

    /** @param array<array-key, array<int, int>> $targets */
    public function run(Project $project, Channel $channel, array $targets): int
    {
        if ($targets === []) {
            return 0;
        }

        $count = 0;
        $accountId = $channel->credentials['channel_id'] ?? null;

        $rows = [];

        try {
            foreach ($this->client->read($channel, array_map(strval(...), array_keys($targets))) as $id => $metrics) {
                foreach ($targets[$id] as $clipId) {
                    $rows[] = [
                        'opus_clip_id' => $clipId,
                        'platform' => $channel->type->value,
                        'media_id' => (string) $id,
                        'observed_on' => now('UTC')->toDateString(),
                        'metrics' => $metrics,
                        'account_id' => is_string($accountId) ? $accountId : null,
                    ];
                    $count++;

                    if (count($rows) === 100) {
                        $batch = $rows;
                        $rows = [];
                        $this->metrics->observe($batch);
                    }
                }
            }
        } finally {
            if ($rows !== []) {
                $this->metrics->observe($rows);
            }
        }

        if ($count > 0 && $project->owner_id !== null && $project->owner_id === $channel->owner_id) {
            WorkspaceUpdated::dispatch($project->owner_id, 'OpusClipMetric', $project->id, (int) now()->format('Uu'));
        }

        return $count;
    }
}
