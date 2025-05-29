<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AuthorizeTiktok extends Command
{
    protected $signature = 'tiktok:auth  {channel_id}';
    protected $description = 'Authenticate with TikTok to retrieve access token';

    public function handle(): void
    {
        $channel = Channel::findOrFail($this->argument('channel_id'));

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

        $response = Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $channel->client_id,
            'client_secret' => $channel->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('home'),
        ]);

        if ($response->failed()) {
            $this->error('Failed to get access token');
            $this->line($response->body());

            return;
        }

        $data = $response->json();

        $this->info('Access Token: ' . $data['access_token']);
        $this->info('Refresh Token: ' . $data['refresh_token']);

        $channel->credentials = $data;
        $channel->save();

        $this->info('✅ Token saved. Channel ready for publishing.');
    }
}
