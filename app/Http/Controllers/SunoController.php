<?php

namespace App\Http\Controllers;

use App\Actions\SaveAudioAction;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\Enums\GenerationStatus;
use App\Http\Requests\SunoRequest;
use Illuminate\Support\Facades\Log;

class SunoController extends Controller
{
    public function __invoke(SunoRequest $request, SaveAudioAction $audioAction): void
    {
        Log::info('SunoController', $request->all());

        $dto = $request->toDto();

        if ($dto->data->callbackType !== GenerationStatus::COMPLETE->value) {
            return;
        }

        $audioAction->run($dto->data);
    }
}
