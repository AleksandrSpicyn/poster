<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Data\VideoCapabilitiesData;
use App\Data\VideoGenerationRequestData;
use App\Data\VideoResultData;
use App\Enums\VideoAudioSource;
use App\Exceptions\MalformedVideoResultException;
use App\Exceptions\VideoGeneratingException;
use App\Exceptions\VideoGenerationPendingException;
use App\Exceptions\VideoGenerationUncertainException;

/**
 * Драйвер нормализует результат, возможности и ошибки своего внешнего API.
 * Общий конвейер не зависит от протокола конкретного провайдера.
 */
interface VideoGenerator
{
    /** @throws VideoGenerationUncertainException Если повторный запуск может создать дубликат. */
    public function generate(VideoGenerationRequestData $request): string;

    public function capabilities(?string $model = null): VideoCapabilitiesData;

    /**
     * @throws VideoGenerationPendingException При временной ошибке опроса.
     * @throws VideoGeneratingException
     * @throws MalformedVideoResultException
     */
    public function check(string $id): ?VideoResultData;

    public function audioSource(): VideoAudioSource;

    public function pollingAttempts(): int;
}
