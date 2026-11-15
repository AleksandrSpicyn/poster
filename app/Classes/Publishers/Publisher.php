<?php

declare(strict_types=1);

namespace App\Classes\Publishers;

/**
 * Граница отправки ролика в канал. true означает принятие операции адаптером;
 * для асинхронного Instagram фактическая публикация завершится позже в job.
 */
interface Publisher
{
    public function publish(): bool;
}
