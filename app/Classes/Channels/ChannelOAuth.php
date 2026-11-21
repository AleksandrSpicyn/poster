<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use App\Classes\Story\Snapshot;
use App\Classes\Tiktok\TiktokClient;
use App\Enums\ChannelType;
use App\Exceptions\ChannelAuthorizationException;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** OAuth protocol boundary. Provider payloads and tokens never reach page props. */
readonly class ChannelOAuth
{
    public function __construct(private TiktokClient $tiktok) {}

    private const string UPLOAD_SCOPE = 'https://www.googleapis.com/auth/youtube.upload';

    private const string READ_SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    /** @return list<string> */
    private function scopes(Channel $channel): array
    {
        return match ($channel->type) {
            ChannelType::YOUTUBE => $channel->external_account === null
                ? [self::UPLOAD_SCOPE, self::READ_SCOPE]
                : [self::READ_SCOPE, 'https://www.googleapis.com/auth/yt-analytics.readonly'],
            ChannelType::TIKTOK => $this->tiktok->scopes($channel),
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
            ChannelType::YOUTUBE => $this->youtube($channel, $code, $redirectUri),
            ChannelType::TIKTOK => $this->tiktok->exchange($channel, $code, $redirectUri),
            ChannelType::INSTAGRAM => $this->instagram($channel, $code, $redirectUri),
            default => throw new RuntimeException('Unsupported platform.'),
        };
    }

    private function expiresIn(mixed $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if ((! is_int($value) && ! is_string($value)) || ! ctype_digit((string) $value) || (int) $value < 1) {
            throw new RuntimeException('Invalid token expiry.');
        }

        return (int) $value;
    }

    /** @return array<string, mixed> */
    private function youtube(Channel $channel, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $channel->oauthClientId(), 'client_secret' => $channel->oauthClientSecret(), 'code' => $code,
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
        ])->throw();
        $token = $response->json('access_token');

        if (! is_string($token) || trim($token) === '') {
            throw new ChannelAuthorizationException('Google returned no access token. Check the OAuth client settings in Poster.');
        }

        $scopes = $response->json('scope');

        if (is_string($scopes) && array_diff($this->scopes($channel), explode(' ', $scopes)) !== []) {
            throw new ChannelAuthorizationException('Grant all requested YouTube permissions when connecting the channel.');
        }

        $query = ['part' => 'snippet', 'mine' => 'true', 'maxResults' => 50];
        do {
            $profile = Http::withToken($token)->timeout(20)->get('https://www.googleapis.com/youtube/v3/channels', $query)->throw();
            $items = $profile->json('items');

            if (! is_array($items)) {
                throw new ChannelAuthorizationException('YouTube returned an incomplete channel list. Check YouTube Data API access for the Poster application.');
            }

            foreach ($items as $item) {
                $id = is_array($item) ? ($item['id'] ?? null) : null;

                if (is_string($id) && $id !== '' && ($channel->external_account === null || $channel->external_account === $id)) {
                    $title = data_get($item, 'snippet.title');

                    break 2;
                }
            }

            $next = $profile->json('nextPageToken');

            if (! is_string($next) || $next === '') {
                throw new ChannelAuthorizationException('YouTube did not return the assigned channel for this login. In the Google account chooser, select the YouTube channel or Brand Account that owns it.');
            }

            $query['pageToken'] = $next;
        } while (true);

        $refresh = $response->json('refresh_token');

        if (! is_string($refresh) || $refresh === '') {
            $refresh = ($channel->credentials['channel_id'] ?? null) === $id ? ($channel->credentials['refresh_token'] ?? null) : null;
        }

        if (! is_string($refresh) || trim($refresh) === '') {
            throw new ChannelAuthorizationException('Google did not grant offline access. Reconnect the assigned channel and approve the consent screen.');
        }

        return ['access_token' => $token, 'refresh_token' => $refresh,
            'token_type' => 'Bearer', 'expires_in' => $this->expiresIn($response->json('expires_in'), 3600), 'created' => now()->timestamp,
            'channel_id' => $id, 'account_name' => is_string($title) && trim($title) !== '' ? $title : $id];
    }

    /** @return array<string, mixed> */
    private function instagram(Channel $channel, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(20)->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $channel->oauthClientId(), 'client_secret' => $channel->oauthClientSecret(), 'code' => $code,
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
        ])->throw();
        $shortToken = Payload::string($response->json('data.0.access_token', $response->json('access_token')), 'access_token');
        $permissions = $response->json('data.0.permissions', $response->json('permissions'));
        $granted = is_string($permissions) ? explode(',', $permissions) : $permissions;

        if ($granted !== null && (! is_array($granted) || array_diff($this->scopes($channel), array_filter($granted, is_string(...))) !== [])) {
            throw new RuntimeException('Required permissions were not granted.');
        }

        $response = Http::timeout(20)->get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token', 'client_secret' => $channel->oauthClientSecret(), 'access_token' => $shortToken,
        ])->throw();
        $token = Payload::string($response->json('access_token'), 'access_token');
        $profile = Http::withToken($token)->timeout(20)->get('https://graph.instagram.com/me', ['fields' => 'user_id,username'])->throw();
        $id = $profile->json('data.0.user_id', $profile->json('user_id'));
        $id = is_int($id) ? (string) $id : $id;

        return ['access_token' => $token, 'channel_id' => Payload::string($id, 'channel_id'),
            'account_name' => Payload::string($profile->json('data.0.username', $profile->json('username')), 'account_name'),
            'expires_at' => now()->addSeconds($this->expiresIn($response->json('expires_in'), 5184000))->toIso8601String()];
    }
}
