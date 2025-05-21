<?php

namespace App\Classes\Publishers;

use App\Classes\Publishers\Instagram\Instagram;
use App\Classes\Publishers\Tiktok\Tiktok;
use App\Classes\Publishers\Vk\Vk;
use App\Classes\Publishers\Youtube\Youtube;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Prompt;

class PublisherFactory
{
    public static function make(Channel $channel, Prompt $prompt): Publisher
    {
        return match ($channel->type) {
            ChannelType::YOUTUBE => new Youtube($channel, $prompt),
            ChannelType::TIKTOK => new Tiktok($channel, $prompt),
            ChannelType::INSTAGRAM => new Instagram($channel, $prompt),
            ChannelType::VK => new Vk($channel, $prompt),
        };
    }
}
