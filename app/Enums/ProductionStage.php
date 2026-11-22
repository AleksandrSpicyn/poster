<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ProductionStage: string
{
    use EnumTrait;

    case PLANNING = 'planning';
    case FINAL_MONTAGE = 'final_montage';
    case FINAL_REVIEW = 'final_review';
    case PREPARING = 'preparing';
    case SCREENPLAY = 'screenplay';
    case MEDIA = 'media';
    case MONTAGE = 'montage';
    case STORY = 'story';
    case PUBLICATION = 'publication';

    public function label(): string
    {
        return match ($this) {
            self::PLANNING => 'План полной версии',
            self::FINAL_MONTAGE => 'Сборка полной версии',
            self::FINAL_REVIEW => 'Проверка полной версии',
            self::PREPARING => 'Подготовка',
            self::SCREENPLAY => 'Сценарий',
            self::MEDIA => 'Создание изображений и видео',
            self::MONTAGE => 'Монтаж',
            self::STORY => 'Сохранение истории',
            self::PUBLICATION => 'Публикация',
        };
    }
}
