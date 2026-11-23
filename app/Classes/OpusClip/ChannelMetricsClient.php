<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Classes\Tiktok\TiktokClient;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ChannelMetricsClient
{
    public function __construct(private YoutubeReadClient $youtubeClient, private TiktokClient $tiktok, private YoutubeMetrics $youtube, private InstagramMetrics $instagram) {}

    /**
     * @param list<string> $references
     * @return Generator<string, array<string, int|float>>
     */
    public function read(Channel $channel, array $references): Generator
    {
        $credentials = $channel->credentials ?? [];
        $account = $channel->type === ChannelType::INSTAGRAM ? ($credentials['account_name'] ?? $credentials['username'] ?? null) : ($credentials['channel_id'] ?? null);

        if (! $channel->is_active || ! is_string($account) || $account === '' || ($channel->external_account !== null && $account !== $channel->external_account)) {
            throw new RuntimeException('Connect the assigned account in Poster.');
        }

        if ($channel->type === ChannelType::TIKTOK) {
            foreach ($this->tiktok->videos($channel, $references) as $video) {
                $metrics = [];

                foreach (['view_count' => 'views', 'like_count' => 'likes', 'comment_count' => 'comments', 'share_count' => 'shares'] as $field => $name) {
                    if (is_int($video[$field] ?? null)) {
                        $metrics[$name] = $video[$field];
                    }
                }

                yield Payload::string($video['id'] ?? null, 'id') => $metrics;
            }

            return;
        }

        $token = Payload::string($credentials['access_token'] ?? null, 'access_token');

        if ($channel->type === ChannelType::YOUTUBE) {
            $http = $this->youtubeClient->forChannel($channel);

            if ($channel->external_account !== null) {
                $remaining = array_fill_keys($references, true);

                foreach ($this->youtube->read($http, $account, $references) as $id => $metrics) {
                    unset($remaining[$id]);

                    yield $id => $metrics;
                }

                $references = array_map(strval(...), array_keys($remaining));
            }

            yield from $this->youtube->statistics($http, $account, $references);

            return;
        }

        $scopes = $credentials['granted_scopes'] ?? null;
        $insights = $channel->external_account !== null || ! is_array($scopes) || in_array('instagram_business_manage_insights', $scopes, true);

        yield from $this->instagram->read(Http::withToken($token), Payload::string($credentials['channel_id'] ?? null, 'channel_id'), $references, $insights, $channel->external_account === null);
    }
}
