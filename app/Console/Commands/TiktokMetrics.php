<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Tiktok\TiktokClient;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Repositories\ChannelRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class TiktokMetrics extends Command
{
    protected $signature = 'tiktok:metrics {channel : Poster channel ID}';

    protected $description = 'Read current views, likes, comments and shares for public videos on a connected TikTok account';

    public function handle(ChannelRepository $channels, TiktokClient $client): int
    {
        $channel = $channels->find($this->argument('channel'));

        if (! $channel instanceof Channel || $channel->type !== ChannelType::TIKTOK || ! $channel->is_active) {
            $this->error('Choose an active TikTok channel.');

            return self::FAILURE;
        }

        try {
            $http = $client->forChannel($channel);
            $params = ['max_count' => 20];
            do {
                $response = $http->post('video/list/?fields=id,title,view_count,like_count,comment_count,share_count', $params);
                $videos = $client->data($response, 'videos');

                if (! is_array($videos)) {
                    throw new RuntimeException('Missing TikTok video list.');
                }

                $rows = [];

                foreach ($videos as $video) {
                    if (! is_array($video)) {
                        throw new RuntimeException('Invalid TikTok video.');
                    }

                    $rows[] = array_map(static fn(string $key): string => is_scalar($video[$key] ?? null) ? (string) $video[$key] : '', ['id', 'title', 'view_count', 'like_count', 'comment_count', 'share_count']);
                }

                $this->table(['Video ID', 'Title', 'Views', 'Likes', 'Comments', 'Shares'], $rows);
                $more = $response->json('data.has_more') === true;
                $cursor = $response->json('data.cursor');

                if ($more && (! is_int($cursor) || $cursor <= 0 || $cursor >= ($params['cursor'] ?? PHP_INT_MAX))) {
                    throw new RuntimeException('Invalid TikTok page cursor.');
                }

                $params['cursor'] = $cursor;
            } while ($more);
        } catch (ConnectionException|RuntimeException) {
            $this->error('Could not read TikTok statistics. Check channel authorization and video.list permission.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
