<?php

namespace App\Classes\Publishers;

use App\Classes\Publishers\Instagram\Instagram;
use App\Classes\Publishers\Tiktok\Tiktok;
use App\Classes\Publishers\Vk\Vk;
use App\Classes\Publishers\Youtube\Youtube;
use App\Enums\ChannelType;

class PublisherFactory
{
    public static function make(ChannelType $channelType): Publisher
    {
        return match ($channelType) {
            ChannelType::YOUTUBE => new Youtube(),
            ChannelType::TIKTOK => new Tiktok(),
            ChannelType::INSTAGRAM => new Instagram(),
            ChannelType::VK => new Vk(),
        };
    }
}
