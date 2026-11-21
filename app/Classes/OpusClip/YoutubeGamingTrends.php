<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Project;
use App\Repositories\OpusWorkspaceRepository;
use App\Support\Integrations\Payload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class YoutubeGamingTrends
{
    public function __construct(private YoutubeReadClient $youtube, private OpusWorkspaceRepository $workspace) {}

    /** @return list<array{title: string, channel: string, views: string, views_per_day?: int|null, url: string}> */
    public function search(string $game): array
    {
        $http = $this->client()->baseUrl('https://www.googleapis.com/youtube/v3');
        $results = $http->get('search', ['part' => 'snippet', 'type' => 'video', 'q' => $game,
            'order' => 'viewCount', 'videoDuration' => 'short', 'videoCategoryId' => '20',
            'publishedAfter' => now('UTC')->subDays(7)->toRfc3339String(), 'maxResults' => 10])->throw();
        $ids = $results->collect('items')->map(fn($item): string => Payload::string(data_get($item, 'id.videoId'), 'videoId'))->all();

        if ($ids === []) {
            return [];
        }

        $videos = $http->get('videos', ['part' => 'snippet,statistics', 'id' => implode(',', $ids)])->throw();

        return array_values($videos->collect('items')->sortByDesc(fn($item): int => is_numeric($views = data_get($item, 'statistics.viewCount')) ? (int) $views : -1)
            ->map(static function ($item): array {
                $views = data_get($item, 'statistics.viewCount');

                $published = data_get($item, 'snippet.publishedAt');
                $ageInDays = is_string($published) ? max(1.0, Carbon::parse($published)->diffInDays(now())) : null;

                return [
                    'title' => Payload::string(data_get($item, 'snippet.title'), 'title'),
                    'channel' => Payload::string(data_get($item, 'snippet.channelTitle'), 'channelTitle'),
                    'views' => is_numeric($views) ? (string) $views : '—',
                    'views_per_day' => $ageInDays !== null && is_numeric($views) ? (int) round((int) $views / $ageInDays) : null,
                    'url' => 'https://www.youtube.com/watch?v=' . rawurlencode(Payload::string(data_get($item, 'id'), 'id')),
                ];
            })->all());
    }

    private function client(): PendingRequest
    {
        $key = config()->string('services.youtube.data_api_key');

        if ($key !== '') {
            return Http::withHeaders(['X-Goog-Api-Key' => $key])->withoutRedirecting()->timeout(30);
        }

        $project = $this->workspace->project();
        $channel = $project instanceof Project ? $this->workspace->channels($project)->first(fn(Channel $item): bool => $item->type === ChannelType::YOUTUBE) : null;

        if ($channel === null) {
            throw new RuntimeException('Connect the gaming YouTube channel or set YOUTUBE_DATA_API_KEY.');
        }

        return $this->youtube->forChannel($channel);
    }
}
