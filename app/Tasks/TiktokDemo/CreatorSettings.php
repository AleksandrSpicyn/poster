<?php

declare(strict_types=1);

namespace App\Tasks\TiktokDemo;

use App\Classes\Tiktok\TiktokClient;
use App\Data\TiktokDemo\CreatorSettingsData;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class CreatorSettings
{
    public function __construct(private TiktokClient $client) {}

    public function run(string $token): CreatorSettingsData
    {
        $response = Http::withToken($token)->withoutRedirecting()->timeout(10)->withBody('{}', 'application/json')
            ->post('https://open.tiktokapis.com/v2/post/publish/creator_info/query/');
        $options = $this->client->data($response, 'privacy_level_options');
        $duration = $response->json('data.max_video_post_duration_sec');

        if (! is_array($options) || ! is_int($duration) || $duration < 1) {
            throw new RuntimeException('Creator unavailable');
        }

        $allowed = config('tiktok_demo.mode') === 'production'
            ? ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'] : ['SELF_ONLY'];

        return new CreatorSettingsData(
            nickname: Payload::string($response->json('data.creator_nickname'), 'nickname'),
            privacy: array_values(array_filter($allowed, static fn(string $level): bool => in_array($level, $options, true))),
            duration: $duration,
            comment: $response->json('data.comment_disabled') === false,
            duet: $response->json('data.duet_disabled') === false,
            stitch: $response->json('data.stitch_disabled') === false,
        );
    }
}
