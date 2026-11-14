<?php

declare(strict_types=1);

namespace App\Classes\Publishers;

use App\Classes\Publishers\Instagram\Instagram;
use App\Classes\Publishers\Tiktok\Tiktok;
use App\Classes\Publishers\Vk\Vk;
use App\Classes\Publishers\Youtube\Youtube;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Prompt;
use Illuminate\Contracts\Container\Container;

readonly class PublisherFactory
{
    public function __construct(private Container $container) {}

    public function make(Channel $channel, Prompt $prompt): Publisher
    {
        $class = match ($channel->type) {
            ChannelType::YOUTUBE => Youtube::class,
            ChannelType::TIKTOK => Tiktok::class,
            ChannelType::INSTAGRAM => Instagram::class,
            ChannelType::VK => Vk::class,
        };

        return $this->container->makeWith($class, ['channel' => $channel, 'prompt' => $prompt]);
    }
}
