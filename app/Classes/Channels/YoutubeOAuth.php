<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use App\Data\Channels\YoutubeChannelData;
use App\Exceptions\ChannelAuthorizationException;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;

final readonly class YoutubeOAuth
{
    /**
     * @param list<string> $requiredScopes
     * @return array<string, mixed>
     */
    public function exchange(Channel $channel, string $code, string $redirectUri, array $requiredScopes): array
    {
        $response = Http::asForm()->timeout(20)->post(config()->string('services.youtube.token_url'), [
            'client_id' => $channel->oauthClientId(),
            'client_secret' => $channel->oauthClientSecret(),
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ])->throw();
        $token = $response->json('access_token');

        if (! is_string($token) || trim($token) === '') {
            throw new ChannelAuthorizationException('Google returned no access token. Check the OAuth client settings in Poster.');
        }

        $scopes = $response->json('scope');

        if (is_string($scopes) && array_diff($requiredScopes, explode(' ', $scopes)) !== []) {
            throw new ChannelAuthorizationException('Grant all requested YouTube permissions when connecting the channel.');
        }

        $profile = $this->findChannel($token, $channel->external_account);
        $refresh = $response->json('refresh_token');

        if (! is_string($refresh) || $refresh === '') {
            $refresh = ($channel->credentials['channel_id'] ?? null) === $profile->id ? ($channel->credentials['refresh_token'] ?? null) : null;
        }

        if (! is_string($refresh) || trim($refresh) === '') {
            throw new ChannelAuthorizationException('Google did not grant offline access. Reconnect the assigned channel and approve the consent screen.');
        }

        return [
            'access_token' => $token,
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'expires_in' => TokenLifetime::seconds($response->json('expires_in'), 3600),
            'created' => now()->timestamp,
            'channel_id' => $profile->id,
            'account_name' => $profile->name,
            'granted_scopes' => is_string($scopes) ? array_values(array_filter(explode(' ', $scopes))) : null,
        ];
    }

    private function findChannel(string $token, ?string $assigned): YoutubeChannelData
    {
        $query = [
            'part' => 'snippet',
            'mine' => 'true',
            'maxResults' => 50,
        ];
        $seen = [];

        do {
            $profile = Http::withToken($token)->timeout(20)->get(config()->string('services.youtube.api_url') . '/channels', $query)->throw();
            $items = $profile->json('items');

            if (! is_array($items)) {
                throw new ChannelAuthorizationException('YouTube returned an incomplete channel list. Check YouTube Data API access for the Poster application.');
            }

            foreach ($items as $item) {
                $id = is_array($item) ? ($item['id'] ?? null) : null;

                if (is_string($id) && $id !== '' && ($assigned === null || $assigned === $id)) {
                    $title = data_get($item, 'snippet.title');

                    return new YoutubeChannelData($id, is_string($title) && trim($title) !== '' ? $title : $id);
                }
            }

            $next = $profile->json('nextPageToken');

            if (! is_string($next) || $next === '') {
                throw new ChannelAuthorizationException('YouTube did not return the assigned channel for this login. In the Google account chooser, select the YouTube channel or Brand Account that owns it.');
            }

            if (isset($seen[$next])) {
                throw new ChannelAuthorizationException('YouTube returned a repeated page of channels. Reconnect the channel to try again.');
            }

            $seen[$next] = true;
            $query['pageToken'] = $next;
        } while (true);
    }
}
