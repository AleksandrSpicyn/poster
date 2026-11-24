<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ChannelType: string
{
    use EnumTrait;

    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case INSTAGRAM = 'instagram';
    case VK = 'vk';

    public function label(): string
    {
        return match ($this) {
            self::YOUTUBE => 'YouTube',
            self::TIKTOK => 'TikTok',
            self::INSTAGRAM => 'Instagram',
            self::VK => 'VK',
        };
    }

    /** @return list<self> */
    public static function oauthProviders(): array
    {
        return [
            self::YOUTUBE,
            self::INSTAGRAM,
            self::TIKTOK,
        ];
    }
}
