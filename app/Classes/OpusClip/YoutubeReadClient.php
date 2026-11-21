<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class YoutubeReadClient
{
    public function forChannel(Channel $channel): PendingRequest
    {
        $credentials = $channel->credentials ?? [];

        if ($channel->type !== ChannelType::YOUTUBE || $channel->external_account === null
            || ($credentials['channel_id'] ?? null) !== $channel->external_account) {
            throw new RuntimeException('Connect the gaming YouTube account in Poster.');
        }

        $created = $credentials['created'] ?? null;
        $expiresIn = $credentials['expires_in'] ?? null;
        $token = $credentials['access_token'] ?? null;

        if (! is_numeric($created) || ! is_numeric($expiresIn) || (int) $created + (int) $expiresIn < now()->getTimestamp() + 60) {
            $token = Http::asForm()->timeout(20)->withoutRedirecting()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $channel->client_id, 'client_secret' => $channel->client_secret,
                'refresh_token' => Payload::string($credentials['refresh_token'] ?? null, 'refresh_token'), 'grant_type' => 'refresh_token',
            ])->throw()->json('access_token');
        }

        return Http::withToken(Payload::string($token, 'access_token'))->withoutRedirecting()->timeout(30);
    }
}
