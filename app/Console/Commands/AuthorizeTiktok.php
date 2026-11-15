<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Support\Integrations\Payload;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Интерактивно сохраняет OAuth-credentials TikTok; наличие токена не означает,
 * что публикация реализована в адаптере Tiktok.
 */
class AuthorizeTiktok extends Command
{
    protected $signature = 'tiktok:auth  {channel_id}';

    protected $description = 'Authenticate with TikTok to retrieve access token';

    /** @throws ConnectionException */
    public function handle(): int
    {
        $channel = Channel::query()->findOrFail($this->argument('channel_id'));

        $scopes = ['user.info.basic'];

        $authUrl = 'https://www.tiktok.com/v2/auth/authorize?' . http_build_query([
            'client_key' => $channel->client_id,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
            'redirect_uri' => route('home'),
            'state' => csrf_token(), // optional
        ]);

        $this->info('Open this URL to authenticate TikTok:');
        $this->line($authUrl);

        $code = $this->ask('Paste the returned ?code=');

        $response = Http::asForm()->connectTimeout(10)->timeout(60)->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $channel->client_id,
            'client_secret' => $channel->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('home'),
        ]);

        if ($response->failed()) {
            $this->error('Failed to get access token');

            return self::FAILURE;
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new UnexpectedValueException('Invalid TikTok token response.');
        }

        Payload::string($data['access_token'] ?? null, 'access_token');
        Payload::string($data['refresh_token'] ?? null, 'refresh_token');

        $channel->credentials = $data;
        $channel->save();

        $this->info('✅ Token saved. Channel ready for publishing.');

        return self::SUCCESS;
    }
}
