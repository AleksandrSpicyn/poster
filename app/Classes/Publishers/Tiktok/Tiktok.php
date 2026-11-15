<?php

declare(strict_types=1);

namespace App\Classes\Publishers\Tiktok;

use App\Classes\Publishers\AbstractPublisher;

/**
 * Заглушка канала TikTok: OAuth-команда существует, публикация ещё не реализована.
 */
class Tiktok extends AbstractPublisher
{
    public function publish(): bool
    {
        // TODO: Implement publish() method.

        return false;
    }
}
