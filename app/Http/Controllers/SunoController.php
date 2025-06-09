<?php

namespace App\Http\Controllers;

use App\Actions\SaveAudioAction;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\Enums\GenerationStatus;
use App\Http\Requests\SunoRequest;

class SunoController extends Controller
{
    public function __invoke(SunoRequest $request, SaveAudioAction $audioAction): void
    {
        $dto = $request->toDto();

        if ($dto->data->callbackType !== GenerationStatus::COMPLETE->value) {
            return;
        }

        $audioAction->run($dto->data);
    }
}
