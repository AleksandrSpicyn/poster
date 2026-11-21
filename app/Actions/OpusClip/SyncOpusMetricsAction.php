<?php

declare(strict_types=1);

namespace App\Actions\OpusClip;

use App\Classes\OpusClip\ChannelMetricsClient;
use App\Classes\OpusClip\VideoReference;
use App\Models\Channel;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use App\Repositories\OpusMetricRepository;

final readonly class SyncOpusMetricsAction
{
    public function __construct(private ChannelMetricsClient $client, private OpusClipRepository $clips, private OpusMetricRepository $metrics) {}

    public function run(Project $project, Channel $channel): int
    {
        $targets = [];

        foreach ($this->clips->forMetrics($project) as $clip) {
            foreach ($clip->publications as $post) {
                if ($post['url'] !== null && ($id = VideoReference::fromUrl($channel->type, $post['url'])) !== null) {
                    $targets[$id][$clip->id] = $clip;
                }
            }
        }

        if ($targets === []) {
            return 0;
        }

        $count = 0;

        foreach ($this->client->read($channel, array_map(strval(...), array_keys($targets))) as $id => $metrics) {
            foreach ($targets[$id] as $clip) {
                $this->metrics->observe($clip, $channel->type, (string) $id, $metrics);
                $count++;
            }
        }

        return $count;
    }
}
