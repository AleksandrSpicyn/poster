<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ProductionStage: string
{
    use EnumTrait;

    case PREPARING = 'preparing';
    case SCREENPLAY = 'screenplay';
    case MEDIA = 'media';
    case MONTAGE = 'montage';
    case STORY = 'story';
    case PUBLICATION = 'publication';

    public function label(): string
    {
        return match ($this) {
            self::PREPARING => 'Preparing',
            self::SCREENPLAY => 'Writing screenplay',
            self::MEDIA => 'Generating images and video',
            self::MONTAGE => 'Building montage',
            self::STORY => 'Saving confirmed story',
            self::PUBLICATION => 'Publishing',
        };
    }
}
