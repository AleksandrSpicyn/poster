<?php

declare(strict_types=1);

namespace App\Classes\Channels;

use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class InstagramOAuth
{
    /**
     * @param list<string> $requiredScopes
     * @return array<string, mixed>
     */
    public function exchange(Channel $channel, string $code, string $redirectUri, array $requiredScopes): array
    {
        $response = Http::asForm()->timeout(20)->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $channel->oauthClientId(), 'client_secret' => $channel->oauthClientSecret(), 'code' => $code,
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
        ])->throw();
        $shortToken = Payload::string($response->json('data.0.access_token', $response->json('access_token')), 'access_token');
        $permissions = $response->json('data.0.permissions', $response->json('permissions'));
        $granted = is_string($permissions) ? explode(',', $permissions) : $permissions;

        if ($granted !== null && (! is_array($granted) || array_diff($requiredScopes, array_filter($granted, is_string(...))) !== [])) {
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
            'granted_scopes' => is_array($granted) ? array_values(array_filter($granted, is_string(...))) : null,
            'expires_at' => now()->addSeconds(TokenLifetime::seconds($response->json('expires_in'), 5184000))->toIso8601String()];
    }
}
