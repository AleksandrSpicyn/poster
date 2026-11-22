<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Classes\OpusClip\ChannelMetricsClient;
use App\Classes\OpusClip\VideoReference;
use App\Data\MetricOriginData;
use App\Data\MetricTargetData;
use App\Enums\ChannelType;
use App\Enums\MetricCollectionStatus;
use App\Events\WorkspaceUpdated;
use App\Models\Channel;
use App\Models\OpusClip;
use App\Models\Post;
use App\Models\User;
use App\Repositories\PostRepository;
use App\Repositories\PublicationObservationRepository;
use Generator;
use Illuminate\Support\LazyCollection;

final readonly class CollectPublicationMetricsAction
{
    public function __construct(private PostRepository $posts, private ChannelMetricsClient $client, private PublicationObservationRepository $observations) {}

    public function run(User $owner, Channel $channel): int
    {
        $account = $channel->credentials['channel_id'] ?? null;

        if ($owner->id !== $channel->owner_id) {
            return 0;
        }

        if (! in_array($channel->type, ChannelType::oauthProviders(), true)) {
            $this->observations->mark($channel, MetricCollectionStatus::UNSUPPORTED);

            return 0;
        }

        if (! $channel->is_active || ! is_string($account) || $account === ''
            || blank($channel->credentials['access_token'] ?? null)
            || ($channel->type === ChannelType::TIKTOK && ! $channel->hasTiktokScope('video.list'))) {
            $this->observations->mark($channel, MetricCollectionStatus::NO_PERMISSION);

            return 0;
        }

        $count = 0;

        foreach (LazyCollection::make(fn(): Generator => $this->targets($owner, $channel, $account))->chunk(100) as $page) {
            $targets = [];

            foreach ($page as $target) {
                $existing = $targets[$target->mediaId] ?? null;
                $targets[$target->mediaId] = new MetricTargetData(
                    $target->mediaId,
                    $existing->title ?? $target->title,
                    [...($existing->origins ?? []), ...$target->origins],
                );
            }

            foreach ($this->client->read($channel, array_map(strval(...), array_keys($targets))) as $id => $metrics) {
                if (isset($targets[$id]) && $this->observations->record($channel, $targets[$id], $account, $metrics)) {
                    $count++;
                }
            }
        }

        $this->observations->mark($channel, $count > 0 ? MetricCollectionStatus::COLLECTED : MetricCollectionStatus::NO_DATA);

        if ($count > 0) {
            WorkspaceUpdated::dispatch($owner->id, 'PublicationObservation', $channel->id, (int) now()->format('Uu'));
        }

        return $count;
    }

    /** @return Generator<int, MetricTargetData> */
    private function targets(User $owner, Channel $channel, string $account): Generator
    {
        foreach ($this->posts->forMetricCollection($owner, $channel) as $post) {
            $reference = VideoReference::fromUrl($channel->type, $post->url ?? '');

            $projectId = $post->prompt->project_id ?? $post->seriesAssembly->series->project_id
                ?? $post->episodeAssembly->episodeRevision->episode->series->project_id ?? null;

            if ($reference !== null && $projectId !== null) {
                yield new MetricTargetData($reference, $post->title, [new MetricOriginData(Post::class, $post->id, $projectId)]);
            }
        }

        foreach ($this->observations->importedClips($owner, $channel, $account) as $clip) {
            foreach ($clip->publications as $publication) {
                $reference = VideoReference::fromUrl($channel->type, $publication['url'] ?? '');

                if ($reference !== null) {
                    yield new MetricTargetData($reference, $clip->title ?? $clip->clip_id, [new MetricOriginData(OpusClip::class, $clip->id, $clip->source->project_id)]);
                }
            }
        }
    }
}
