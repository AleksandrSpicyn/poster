<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChannelType;
use App\Support\Integrations\Payload;
use App\Tasks\Channels\LoadChannel;
use App\Tasks\Channels\SaveChannelCredentials;
use Google\Client;
use Google\Service\YouTube;
use Illuminate\Console\Command;

/**
 * Интерактивно обменивает OAuth-код на credentials выбранного YouTube-канала.
 * Это подготовка канала, загрузку роликов выполняет Youtube Publisher.
 */
class AuthorizeYoutube extends Command
{
    protected $signature = 'youtube:auth {channel_id} {--with-read-verification : Also request read access to verify uncertain publications}';

    protected $description = 'Authorize a YouTube channel using OAuth 2.0';

    public function handle(Client $client, LoadChannel $load, SaveChannelCredentials $save): int
    {
        $channel = $load->run($this->argument('channel_id'));

        if ($channel->type !== ChannelType::YOUTUBE) {
            $this->error('Choose a YouTube channel.');

            return self::FAILURE;
        }

        $client->setClientId(Payload::string($channel->oauthClientId(), 'client_id'));
        $client->setClientSecret(Payload::string($channel->oauthClientSecret(), 'client_secret'));
        $client->setRedirectUri(route('home'));

        $scopes = [YouTube::YOUTUBE_UPLOAD];

        if ($this->option('with-read-verification')) {
            $scopes[] = YouTube::YOUTUBE_READONLY;
        }

        $client->setScopes($scopes);
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

        $save->run($channel, $accessToken);

        $this->info('✅ Token saved. Channel ready for publishing.');

        return self::SUCCESS;
    }
}
