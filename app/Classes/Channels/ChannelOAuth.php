<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use App\Classes\Story\Snapshot;
use App\Classes\Tiktok\TiktokClient;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use RuntimeException;

/** OAuth protocol boundary. Provider payloads and tokens never reach page props. */
readonly class ChannelOAuth
{
    public function __construct(private TiktokClient $tiktok, private YoutubeOAuth $youtube, private InstagramOAuth $instagram) {}

    private const string UPLOAD_SCOPE = 'https://www.googleapis.com/auth/youtube.upload';

    private const string READ_SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    /** @return list<string> */
    private function scopes(Channel $channel): array
    {
        return match ($channel->type) {
            ChannelType::YOUTUBE => $channel->external_account === null
                ? [self::UPLOAD_SCOPE, self::READ_SCOPE]
                : [self::READ_SCOPE, 'https://www.googleapis.com/auth/yt-analytics.readonly'],
            ChannelType::TIKTOK => $this->tiktok->scopes(),
            ChannelType::INSTAGRAM => ['instagram_business_basic', $channel->external_account === null
                ? 'instagram_business_content_publish' : 'instagram_business_manage_insights'],
            default => throw new RuntimeException('Unsupported platform.'),
        };
    }

    public function fingerprint(Channel $channel): string
    {
        return Snapshot::hash([$channel->id, $channel->type->value, $channel->oauthClientId(), $channel->oauthClientSecret(), $channel->external_account, $channel->credentials]);
    }

    public function authorizationUrl(Channel $channel, string $state): string
    {
        $redirectUri = route('channels.provider-callback', $channel->type);
        $key = $channel->type === ChannelType::TIKTOK ? 'client_key' : 'client_id';
        $params = [$key => Payload::string($channel->oauthClientId(), 'client_id'),
            'redirect_uri' => $redirectUri, 'response_type' => 'code', 'state' => $state];
        Payload::string($channel->oauthClientSecret(), 'client_secret');

        return match ($channel->type) {
            ChannelType::YOUTUBE => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                ...$params, 'scope' => implode(' ', $this->scopes($channel)), 'access_type' => 'offline', 'prompt' => $channel->external_account === null ? 'consent' : 'select_account consent',
            ]),
            ChannelType::TIKTOK => 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
                ...$params, 'scope' => implode(',', $this->scopes($channel)),
            ]),
            ChannelType::INSTAGRAM => 'https://www.instagram.com/oauth/authorize?' . http_build_query([
                ...$params, 'scope' => implode(',', $this->scopes($channel)), 'enable_fb_login' => 'false',
            ]),
            default => throw new RuntimeException('OAuth is not available for this platform.'),
        };
    }

    /** @return array<string, mixed> */
    public function exchange(Channel $channel, string $code, string $redirectUri): array
    {
        return match ($channel->type) {
            ChannelType::YOUTUBE => $this->youtube->exchange($channel, $code, $redirectUri, $this->scopes($channel)),
            ChannelType::TIKTOK => $this->tiktok->exchange($channel, $code, $redirectUri),
            ChannelType::INSTAGRAM => $this->instagram->exchange($channel, $code, $redirectUri, $this->scopes($channel)),
            default => throw new RuntimeException('Unsupported platform.'),
        };
    }
}
