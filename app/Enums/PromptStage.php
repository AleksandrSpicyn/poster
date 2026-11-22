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
            self::IMAGE => 'Ожидание кадра',
            self::STARTING_MEDIA => 'Подготовка видео и звука',
            self::MEDIA => 'Ожидание видео и звука',
            self::VIDEO => 'Ожидание видео',
            self::AUDIO => 'Ожидание звука',
            self::ASSEMBLY => 'Сборка',
            self::READY => 'Видео готово',
        };
    }
}
