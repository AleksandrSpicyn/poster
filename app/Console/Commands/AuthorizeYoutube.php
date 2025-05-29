<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Google\Client;
use Illuminate\Console\Command;

class AuthorizeYoutube extends Command
{
    protected $signature = 'youtube:auth {channel_id}';
    protected $description = 'Authorize a YouTube channel using OAuth 2.0';

    public function handle(): void
    {
        $channel = Channel::findOrFail($this->argument('channel_id'));

        $client = new Client();
        $client->setClientId($channel->client_id);
        $client->setClientSecret($channel->client_secret);
        $client->setRedirectUri(route('home'));
        $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        $this->info("Open the link and log in:\n\n$authUrl\n");

        $code = $this->ask('Paste the authorization code from the browse');

        $accessToken = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($accessToken['error'])) {
            $this->error('Authorization error: ' . $accessToken['error_description']);

            return;
        }

        $channel->credentials = $accessToken;
        $channel->save();

        $this->info('✅ Token saved. Channel ready for publishing.');
    }
}
