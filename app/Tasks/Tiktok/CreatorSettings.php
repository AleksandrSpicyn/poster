<?php

declare(strict_types=1);

namespace App\Tasks\Tiktok;

use App\Classes\Tiktok\TiktokClient;
use App\Data\Tiktok\CreatorSettingsData;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

final readonly class CreatorSettings
{
    public function __construct(private TiktokClient $client) {}

    public function available(Channel $channel): ?CreatorSettingsData
    {
        try {
            $settings = $this->run($channel);
        } catch (RuntimeException|ConnectionException) {
            return null;
        }

        return $settings->privacy === [] ? null : $settings;
    }

    public function run(Channel $channel): CreatorSettingsData
    {
        $response = $this->client->forChannel($channel, 'video.publish')->timeout(10)->withBody('{}', 'application/json')
            ->post('post/publish/creator_info/query/');
        $options = $this->client->data($response, 'privacy_level_options');
        $duration = $response->json('data.max_video_post_duration_sec');

        if (! is_array($options) || ! is_int($duration) || $duration < 1) {
            throw new RuntimeException('Creator unavailable');
        }

        $allowed = config()->boolean('services.tiktok.public_direct_post')
            ? [
                'PUBLIC_TO_EVERYONE',
                'MUTUAL_FOLLOW_FRIENDS',
                'FOLLOWER_OF_CREATOR',
                'SELF_ONLY',
            ] : ['SELF_ONLY'];

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
