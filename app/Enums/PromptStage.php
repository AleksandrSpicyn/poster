<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

/** Этап по сохранённым результатам; не подтверждает работу или отказ внешнего worker. */
enum PromptStage: string
{
    use EnumTrait;

    case IMAGE = 'image';
    case STARTING_MEDIA = 'starting_media';
    case MEDIA = 'media';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case ASSEMBLY = 'assembly';
    case READY = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Awaiting image',
            self::STARTING_MEDIA => 'Starting media',
            self::MEDIA => 'Awaiting video & audio',
            self::VIDEO => 'Awaiting video',
            self::AUDIO => 'Awaiting audio',
            self::ASSEMBLY => 'Awaiting assembly',
            self::READY => 'Video ready',
        };
    }
}
