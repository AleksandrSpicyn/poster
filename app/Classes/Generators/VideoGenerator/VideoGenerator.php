<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Data\VideoResultData;
use App\Enums\VideoAudioSource;
use App\Exceptions\VideoGenerationPendingException;
use App\Exceptions\VideoGenerationUncertainException;

/**
 * Драйвер нормализует результат, возможности и ошибки своего внешнего API.
 * Общий конвейер не зависит от протокола конкретного провайдера.
 */
interface VideoGenerator
{
    /** @throws VideoGenerationUncertainException Если повторный запуск может создать дубликат. */
    public function generate(string $imageUrl, string $prompt): string;

    /** @throws VideoGenerationPendingException При временной ошибке опроса. */
    public function check(string $id): ?VideoResultData;

    public function audioSource(): VideoAudioSource;

    public function pollingAttempts(): int;
}
