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
        $client->setRedirectUri(config('services.ngrok.url')); // консольный flow
        $client->setScopes(['https://www.googleapis.com/auth/youtube.upload']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        $authUrl = $client->createAuthUrl();
        $this->info("Открой ссылку и авторизуйся:\n\n$authUrl\n");

        $code = $this->ask('Вставь authorization code из браузера');

        $accessToken = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($accessToken['error'])) {
            $this->error('Ошибка авторизации: ' . $accessToken['error_description']);

            return;
        }

        $channel->credentials = $accessToken;
        $channel->save();

        $this->info('✅ Токен сохранён. Канал готов к публикации.');
    }
}
