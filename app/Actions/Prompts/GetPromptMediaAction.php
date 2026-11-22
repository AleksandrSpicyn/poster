<?php

declare(strict_types=1);

namespace App\Actions\Prompts;

use App\Models\Prompt;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetPromptMediaAction
{
    public function run(Prompt $prompt, Media $media): string
    {
        abort_unless($prompt->generated && $prompt->shot_id === null && $media->collection_name === 'default'
            && $media->model_type === $prompt->getMorphClass() && $media->model_id === $prompt->id
            && in_array($media->disk, ['local', 'public'], true), 404);

        $path = $media->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }
}
