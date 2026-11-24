<?php

declare(strict_types=1);

namespace App\Actions\Prompts;

use App\Enums\UpscaleOperationStatus;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetPromptMediaAction
{
    public function run(Prompt $prompt, Media $media): string
    {
        abort_unless($prompt->generated && $prompt->shot_id === null && is_file($media->getPath()) && ($media->collection_name === 'default' || ($media->collection_name === EpisodeAssembly::UPSCALED && $prompt->upscales()->whereNull('parent_id')->where('result_media_id', $media->id)->where('result_sha256', hash_file('sha256', $media->getPath()))->where('status', UpscaleOperationStatus::READY)->exists()))
            && $media->model_type === $prompt->getMorphClass() && $media->model_id === $prompt->id
            && in_array($media->disk, ['local', 'public'], true), 404);

        $path = $media->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }
}
