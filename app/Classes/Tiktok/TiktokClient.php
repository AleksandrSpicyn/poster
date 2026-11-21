<?php

declare(strict_types=1);

namespace App\Classes\Tiktok;

use App\Enums\ChannelType;
use App\Exceptions\ChannelAuthorizationException;
use App\Models\Channel;
use App\Repositories\ChannelRepository;
use App\Support\Integrations\Payload;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class TiktokClient
{
    public function __construct(private ChannelRepository $channels) {}

    /** @return list<string> */
    public function scopes(): array
    {
        return ['user.info.basic', 'video.list', 'video.upload'];
    }

    /**
     * @param list<string>|null $requiredScopes
     * @return array<string, mixed>
     */
    public function exchange(Channel $channel, string $code, string $redirectUri, ?array $requiredScopes = null): array
    {
        $tokens = $this->token($channel, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri]);
        $scope = Payload::string($tokens['scope'] ?? null, 'scope');

        if (array_diff($requiredScopes ?? $this->scopes(), explode(',', $scope)) !== []) {
            throw new ChannelAuthorizationException('Grant the requested TikTok permissions to connect this account.');
        }

        $profile = $this->api(Payload::string($tokens['access_token'], 'access_token'))->get('user/info/', ['fields' => 'open_id,display_name']);

        if ($this->data($profile, 'user.open_id') !== $tokens['open_id']) {
            throw new ChannelAuthorizationException('TikTok could not confirm this account.');
        }

        return [...$tokens, 'channel_id' => $tokens['open_id'], 'account_name' => Payload::string($this->data($profile, 'user.display_name'), 'display_name')];
    }

    /** @param array<string,string> $grant
     * @return array<string,mixed>
     */
    private function token(Channel $channel, array $grant): array
    {
        $response = Http::asForm()->withoutRedirecting()->timeout(20)->post('https://open.tiktokapis.com/v2/oauth/token/', [
            ...$grant, 'client_key' => $channel->oauthClientId(), 'client_secret' => $channel->oauthClientSecret(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('TikTok token exchange failed. Reconnect the channel.');
        }

        $expiry = $response->json('expires_in');

        if (! is_int($expiry) || $expiry <= 0) {
            throw new RuntimeException('Invalid TikTok token expiry.');
        }

        return ['access_token' => Payload::string($response->json('access_token'), 'access_token'),
            'refresh_token' => Payload::string($response->json('refresh_token'), 'refresh_token'),
            'open_id' => Payload::string($response->json('open_id'), 'open_id'), 'scope' => $response->json('scope'),
            'expires_at' => now()->addSeconds($expiry)->toIso8601String()];
    }

    public function forChannel(Channel $channel): PendingRequest
    {
        $token = DB::transaction(function () use ($channel): string {
            $current = $this->channels->findLockedOrFail($channel->id);
            $credentials = $current->credentials ?? [];
            $id = Payload::string($credentials['channel_id'] ?? null, 'channel_id');

            if ($current->type !== ChannelType::TIKTOK || ! $current->is_active || $id !== ($channel->credentials['channel_id'] ?? null)
                || ($current->external_account !== null && $current->external_account !== $id)) {
                throw new RuntimeException('Reconnect the assigned TikTok account.');
            }

            if (Carbon::parse(Payload::string($credentials['expires_at'] ?? null, 'expires_at'))->getTimestamp() > now()->getTimestamp() + 60) {
                return Payload::string($credentials['access_token'] ?? null, 'access_token');
            }

            $tokens = $this->token($current, ['grant_type' => 'refresh_token', 'refresh_token' => Payload::string($credentials['refresh_token'] ?? null, 'refresh_token')]);

            if ($tokens['open_id'] !== $id) {
                throw new RuntimeException('TikTok refresh returned a different account.');
            }

            $this->channels->update($current, ['credentials' => [...$credentials, ...$tokens]]);

            return Payload::string($tokens['access_token'], 'access_token');
        });

        return $this->api($token);
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)->withoutRedirecting()->timeout(30)->baseUrl('https://open.tiktokapis.com/v2/');
    }

    public function data(Response $response, string $field): mixed
    {
        if (! $response->successful() || $response->json('error.code') !== 'ok') {
            throw new RuntimeException('TikTok API request failed (HTTP ' . $response->status() . ').');
        }

        return $response->json('data.' . $field);
    }

    /** @param list<string> $ids
     * @return list<array<array-key,mixed>>
     */
    public function videos(Channel $channel, array $ids): array
    {
        $http = $this->forChannel($channel);
        $videos = [];

        foreach (array_chunk($ids, 20) as $batch) {
            $response = $http->post('video/query/?fields=id,title,share_url,view_count,like_count,comment_count,share_count', ['filters' => ['video_ids' => $batch]]);
            $items = $this->data($response, 'videos');

            if (! is_array($items)) {
                throw new RuntimeException('Missing TikTok video list.');
            }

            foreach ($items as $item) {
                if (is_array($item) && in_array($item['id'] ?? null, $batch, true)) {
                    $videos[] = $item;
                }
            }
        }

        return $videos;
    }
}
