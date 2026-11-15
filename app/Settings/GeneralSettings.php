<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Общая длительность ролика в секундах для генераторов и финального FFmpeg-экспорта.
 * Значение хранится пакетом Laravel Settings в группе general.
 */
class GeneralSettings extends Settings
{
    public int $duration;

    public static function group(): string
    {
        return 'general';
    }
}
