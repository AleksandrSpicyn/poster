<?php

declare(strict_types=1);

namespace App\Http\Controllers\Prompts;

use App\Actions\Prompts\GetPromptMediaAction;
use App\Models\Prompt;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController
{
    public function __invoke(Prompt $prompt, Media $media, GetPromptMediaAction $action): BinaryFileResponse
    {
        return response()->file($action->run($prompt, $media), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }
}
