<?php

namespace App\Classes\Publishers;

use App\Classes\Publishers\Instagram\Instagram;
use App\Classes\Publishers\Tiktok\Tiktok;
use App\Classes\Publishers\Vk\Vk;
use App\Classes\Publishers\Youtube\Youtube;
use App\Enums\ChannelType;
use App\Models\Channel;

class PublisherFactory
{
    public static function make(Channel $channel): Publisher
    {
        return match ($channel->type) {
            ChannelType::YOUTUBE => new Youtube($channel),
            ChannelType::TIKTOK => new Tiktok($channel),
            ChannelType::INSTAGRAM => new Instagram($channel),
            ChannelType::VK => new Vk($channel),
        };
    }
}
