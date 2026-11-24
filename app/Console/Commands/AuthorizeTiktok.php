<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Channels\ChannelOAuth;
use App\Enums\ChannelType;
use App\Support\Integrations\Payload;
use App\Tasks\Channels\LoadChannel;
use App\Tasks\Channels\SaveChannelCredentials;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * Совместимость с консольным подключением через зарегистрированный главный URL.
 */
class AuthorizeTiktok extends Command
{
    protected $signature = 'tiktok:auth  {channel_id}';

    protected $description = 'Authenticate with TikTok to retrieve access token';

    /** @throws ConnectionException */
    public function handle(LoadChannel $load, SaveChannelCredentials $save, ChannelOAuth $oauth): int
    {
        $channel = $load->run($this->argument('channel_id'));

        if ($channel->type !== ChannelType::TIKTOK) {
            $this->error('Choose a TikTok channel.');

            return self::FAILURE;
        }

        $redirectUri = route('home');
        $authUrl = $oauth->authorizationUrl($channel, csrf_token(), $redirectUri);

        $this->info('Open this URL to authenticate TikTok:');
        $this->line($authUrl);

        $code = $this->ask('Paste the returned ?code=');

        try {
            $data = $oauth->exchange($channel, Payload::string($code, 'code'), $redirectUri);

            if ($channel->external_account !== null && $channel->external_account !== $data['open_id']) {
                throw new RuntimeException('The account does not match the configured identity.');
            }
        } catch (RuntimeException|ConnectionException) {
            $this->error('TikTok connection failed. Check the selected account and permissions.');

            return self::FAILURE;
        }

        $save->run($channel, $data);

        $this->info('TikTok account connected.');

        return self::SUCCESS;
    }
}
