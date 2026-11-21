<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Classes\Tiktok\TiktokClient;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use UnexpectedValueException;

final readonly class ChannelMetricsClient
{
    public function __construct(private YoutubeReadClient $youtubeClient, private TiktokClient $tiktok) {}

    public function reference(ChannelType $type, string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if ($type === ChannelType::TIKTOK && in_array($host, ['www.tiktok.com', 'tiktok.com', 'm.tiktok.com'], true)
            && preg_match('~^/@[^/]+/video/([0-9]{1,20})/?$~D', $path, $match)) {
            return $match[1];
        }

        if ($type === ChannelType::INSTAGRAM && in_array($host, ['instagram.com', 'www.instagram.com'], true)
            && preg_match('~^/(?:reels?|p)/([\w-]+)/?$~D', $path, $match)) {
            return $match[1];
        }

        if ($type !== ChannelType::YOUTUBE || ! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $id = match (true) {
            $host === 'youtu.be' => trim($path, '/'),
            $path === '/watch' => $params['v'] ?? null,
            str_starts_with($path, '/shorts/') => trim(substr($path, 8), '/'),
            default => null,
        };

        return is_string($id) && preg_match('/^[\w-]{11}$/D', $id) ? $id : null;
    }

    /**
     * @param list<string> $references
     * @return Generator<string, array<string, int|float>>
     */
    public function read(Channel $channel, array $references): Generator
    {
        $credentials = $channel->credentials ?? [];
        $account = $channel->type === ChannelType::INSTAGRAM ? ($credentials['account_name'] ?? null) : ($credentials['channel_id'] ?? null);

        if (! is_string($account) || $account !== $channel->external_account) {
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
            yield from $this->youtube($this->youtubeClient->forChannel($channel), $account, $references);

            return;
        }

        yield from $this->instagram(Http::withToken($token), Payload::string($credentials['channel_id'] ?? null, 'channel_id'), $references);
    }

    /**
     * @param list<string> $references
     * @return Generator<string, array<string, int|float>>
     */
    private function youtube(PendingRequest $http, string $account, array $references): Generator
    {
        foreach (array_chunk($references, 100) as $ids) {
            $report = $http->withoutRedirecting()->timeout(30)->get('https://youtubeanalytics.googleapis.com/v2/reports', [
                'ids' => 'channel==' . $account, 'startDate' => '2005-01-01', 'endDate' => now('America/Los_Angeles')->toDateString(),
                'metrics' => 'views,engagedViews,averageViewDuration,averageViewPercentage,shares', 'dimensions' => 'video',
                'filters' => 'video==' . implode(',', $ids), 'maxResults' => count($ids),
            ])->throw();
            $headers = $report->json('columnHeaders');

            if (! is_array($headers)) {
                throw new UnexpectedValueException('Missing YouTube report headers.');
            }

            $columns = array_map(static fn($header): string => Payload::string(data_get($header, 'name'), 'column name'), $headers);
            $rows = $report->json('rows', []);

            if (! is_array($rows)) {
                throw new UnexpectedValueException('Invalid YouTube report.');
            }

            foreach ($rows as $row) {
                if (! is_array($row) || count($row) !== count($columns)) {
                    throw new UnexpectedValueException('Invalid YouTube report row.');
                }

                $metrics = array_combine($columns, $row);
                $id = Payload::string($metrics['video'] ?? null, 'video');
                unset($metrics['video']);

                if (in_array($id, $ids, true)) {
                    yield $id => array_filter($metrics, static fn($value): bool => is_int($value) || is_float($value));
                }
            }
        }
    }

    /**
     * @param list<string> $references
     * @return Generator<string, array<string, int|float>>
     */
    private function instagram(PendingRequest $http, string $account, array $references): Generator
    {
        $http->withoutRedirecting()->timeout(30)->baseUrl('https://graph.instagram.com/' . config()->string('services.instagram.version'));
        $remaining = array_fill_keys($references, true);
        $seen = [];
        $params = ['fields' => 'id,permalink', 'limit' => 100];

        do {
            $page = $http->get(rawurlencode($account) . '/media', $params)->throw();

            foreach ($page->collect('data') as $media) {
                $ref = $this->reference(ChannelType::INSTAGRAM, Payload::string(data_get($media, 'permalink'), 'permalink'));

                if ($ref === null || ! isset($remaining[$ref])) {
                    continue;
                }

                $id = Payload::string(data_get($media, 'id'), 'id');
                $response = $http->get(rawurlencode($id) . '/insights', ['metric' => 'views,reach,ig_reels_avg_watch_time,shares,saved'])->throw();
                $metrics = [];

                foreach ($response->collect('data') as $metric) {
                    $value = data_get($metric, 'values.0.value');

                    if (is_int($value) || is_float($value)) {
                        $name = Payload::string(data_get($metric, 'name'), 'name');

                        if ($name === 'ig_reels_avg_watch_time') {
                            $metrics['averageViewDuration'] = $value / 1000;
                        } else {
                            $metrics[$name] = $value;
                        }
                    }
                }

                if ($metrics !== []) {
                    yield $ref => $metrics;
                }

                unset($remaining[$ref]);
            }

            if ($remaining === [] || ! $page->json('paging.next')) {
                return;
            }

            $cursor = Payload::string($page->json('paging.cursors.after'), 'paging.cursors.after');

            if (isset($seen[$cursor])) {
                throw new UnexpectedValueException('Repeated Instagram media cursor.');
            }

            $params['after'] = $cursor;
            $seen[$cursor] = true;
        } while (true);
    }
}
