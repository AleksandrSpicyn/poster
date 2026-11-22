<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum MetricCollectionStatus: string
{
    use EnumTrait;

    case COLLECTED = 'collected';
    case NO_DATA = 'no_data';
    case NO_PERMISSION = 'no_permission';
    case UNSUPPORTED = 'unsupported';
    case TEMPORARY_ERROR = 'temporary_error';
    case RATE_LIMITED = 'rate_limited';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTED => 'Данные получены',
            self::NO_DATA => 'Нет доступных наблюдений',
            self::NO_PERMISSION => 'Требуется подключение с правами чтения',
            self::UNSUPPORTED => 'Сбор пока недоступен для площадки',
            self::RATE_LIMITED => 'Пауза по лимиту API',
            self::TEMPORARY_ERROR => 'Временная ошибка сбора',
        };
    }
}
