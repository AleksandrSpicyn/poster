<?php

namespace App\Actions;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\CallbackData;
use App\Tasks\SaveAudio;

class SaveAudioAction extends Action
{
    public function __construct(private readonly SaveAudio $saveAudio)
    {
    }

    public function run(CallbackData $dto): void
    {
        $this->saveAudio->run($dto->taskId, $dto->tasks->first());
    }
}
