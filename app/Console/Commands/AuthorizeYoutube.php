<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Support\Integrations\Payload;
use Google\Client;
use Illuminate\Console\Command;

/**
 * Интерактивно обменивает OAuth-код на credentials выбранного YouTube-канала.
 * Это подготовка канала, загрузку роликов выполняет Youtube Publisher.
 */
class AuthorizeYoutube extends Command
{
    protected $signature = 'youtube:auth {channel_id}';

    protected $description = 'Authorize a YouTube channel using OAuth 2.0';

    public function handle(Client $client): int
    {
        $channel = Channel::query()->findOrFail($this->argument('channel_id'));

        $client->setClientId(Payload::string($channel->client_id, 'client_id'));
        $client->setClientSecret(Payload::string($channel->client_secret, 'client_secret'));
        $client->setRedirectUri(route('home'));
        $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        $this->info("Open the link and log in:\n\n$authUrl\n");

        $code = $this->ask('Paste the authorization code from the browse');

        $accessToken = $client->fetchAccessTokenWithAuthCode(Payload::string($code, 'authorization_code'));

        if (isset($accessToken['error'])) {
            $this->error('YouTube authorization failed.');

            return self::FAILURE;
        }

        $channel->credentials = $accessToken;
        $channel->save();

        $this->info('✅ Token saved. Channel ready for publishing.');

        return self::SUCCESS;
    }
}
