<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use App\Classes\Story\Snapshot;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** OAuth protocol boundary. Provider payloads and tokens never reach page props. */
readonly class ChannelOAuth
{
    private const string UPLOAD_SCOPE = 'https://www.googleapis.com/auth/youtube.upload';

    private const string READ_SCOPE = 'https://www.googleapis.com/auth/youtube.readonly';

    public function fingerprint(Channel $channel): string
    {
        return Snapshot::hash([$channel->id, $channel->type->value, $channel->client_id, $channel->client_secret, $channel->credentials]);
    }

    public function authorizationUrl(Channel $channel, string $state): string
    {
        $params = ['client_id' => Payload::string($channel->client_id, 'client_id'),
            'redirect_uri' => route('channels.callback', $channel), 'response_type' => 'code', 'state' => $state];
        Payload::string($channel->client_secret, 'client_secret');

        return match ($channel->type) {
            ChannelType::YOUTUBE => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                ...$params, 'scope' => self::UPLOAD_SCOPE . ' ' . self::READ_SCOPE, 'access_type' => 'offline', 'prompt' => 'consent',
            ]),
            ChannelType::INSTAGRAM => 'https://www.instagram.com/oauth/authorize?' . http_build_query([
                ...$params, 'scope' => 'instagram_business_basic,instagram_business_content_publish', 'enable_fb_login' => 'false',
            ]),
            default => throw new RuntimeException('OAuth is not available for this platform.'),
        };
    }

    /** @return array<string, mixed> */
    public function exchange(Channel $channel, string $code): array
    {
        return match ($channel->type) {
            ChannelType::YOUTUBE => $this->youtube($channel, $code),
            ChannelType::INSTAGRAM => $this->instagram($channel, $code),
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
    private function youtube(Channel $channel, string $code): array
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $channel->client_id, 'client_secret' => $channel->client_secret, 'code' => $code,
            'redirect_uri' => route('channels.callback', $channel), 'grant_type' => 'authorization_code',
        ])->throw();
        $token = Payload::string($response->json('access_token'), 'access_token');
        $scopes = $response->json('scope');

        if (is_string($scopes) && (! in_array(self::UPLOAD_SCOPE, explode(' ', $scopes), true) || ! in_array(self::READ_SCOPE, explode(' ', $scopes), true))) {
            throw new RuntimeException('Required permissions were not granted.');
        }

        $profile = Http::withToken($token)->timeout(20)->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true'])->throw();
        $id = Payload::string($profile->json('items.0.id'), 'channel_id');
        $refresh = $response->json('refresh_token');

        if (! is_string($refresh) || $refresh === '') {
            $refresh = ($channel->credentials['channel_id'] ?? null) === $id ? ($channel->credentials['refresh_token'] ?? null) : null;
        }

        return ['access_token' => $token, 'refresh_token' => Payload::string($refresh, 'refresh_token'),
            'token_type' => 'Bearer', 'expires_in' => $this->expiresIn($response->json('expires_in'), 3600), 'created' => now()->timestamp,
            'channel_id' => $id, 'account_name' => Payload::string($profile->json('items.0.snippet.title'), 'account_name')];
    }

    /** @return array<string, mixed> */
    private function instagram(Channel $channel, string $code): array
    {
        $response = Http::asForm()->timeout(20)->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $channel->client_id, 'client_secret' => $channel->client_secret, 'code' => $code,
            'redirect_uri' => route('channels.callback', $channel), 'grant_type' => 'authorization_code',
        ])->throw();
        $shortToken = Payload::string($response->json('data.0.access_token', $response->json('access_token')), 'access_token');
        $permissions = $response->json('data.0.permissions', $response->json('permissions'));
        $granted = is_string($permissions) ? explode(',', $permissions) : $permissions;

        if ($granted !== null && (! is_array($granted) || ! in_array('instagram_business_basic', $granted, true) || ! in_array('instagram_business_content_publish', $granted, true))) {
            throw new RuntimeException('Required permissions were not granted.');
        }

        $response = Http::timeout(20)->get('https://graph.instagram.com/access_token', [
            'grant_type' => 'ig_exchange_token', 'client_secret' => $channel->client_secret, 'access_token' => $shortToken,
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
