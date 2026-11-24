<?php

declare(strict_types=1);

namespace App\Http\Controllers\Prompts;

use App\Actions\Prompts\GetPromptMediaAction;
use App\Http\Controllers\Controller;
use App\Models\Prompt;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function __invoke(Prompt $prompt, Media $media, GetPromptMediaAction $action): BinaryFileResponse
    {
        return $this->privateFile($action->run($prompt, $media));
    }
}
