<?php

declare(strict_types=1);

namespace App\Actions;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\CallbackData;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\Enums\GenerationStatus;
use App\Tasks\SaveAudio;

/**
 * Принимает завершённый callback Suno и передаёт первый трек на сохранение.
 * Промежуточные стадии и callback без треков не запускают продолжение конвейера.
 */
readonly class SaveAudioAction
{
    public function __construct(private SaveAudio $saveAudio) {}

    public function run(CallbackData $dto): void
    {
        if ($dto->callbackType !== GenerationStatus::COMPLETE->value) {
            return;
        }

        // Suno возвращает варианты трека, но одной генерации Prompt соответствует один audio_url.
        $task = $dto->tasks->first();

        if ($task === null) {
            return;
        }

        $this->saveAudio->run($dto->taskId, $task);
    }
}
