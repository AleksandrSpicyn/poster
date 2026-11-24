<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Vk;

use App\Classes\Publishers\AbstractPublisher;

/**
 * Заглушка канала VK; false сообщает вызывающему коду об отсутствии публикации.
 */
class Vk extends AbstractPublisher
{
    public function publish(): bool
    {
        return false;
    }
}
