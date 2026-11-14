<?php

declare(strict_types=1);

namespace App\Actions;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\CallbackData;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\Enums\GenerationStatus;
use App\Tasks\SaveAudio;

readonly class SaveAudioAction
{
    public function __construct(private SaveAudio $saveAudio) {}

    public function run(CallbackData $dto): void
    {
        if ($dto->callbackType !== GenerationStatus::COMPLETE->value) {
            return;
        }

        $task = $dto->tasks->first();

        if ($task === null) {
            return;
        }

        $this->saveAudio->run($dto->taskId, $task);
    }
}
