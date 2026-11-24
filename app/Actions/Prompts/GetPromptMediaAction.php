<?php

declare(strict_types=1);

namespace App\Actions\Prompts;

use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Repositories\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetPromptMediaAction
{
    public function __construct(private MediaRepository $files) {}

    public function run(Prompt $prompt, Media $media): string
    {
        abort_unless($prompt->generated && $prompt->shot_id === null
            && $media->model_type === $prompt->getMorphClass() && $media->model_id === $prompt->id
            && in_array($media->disk, ['local', 'public'], true), 404);

        $path = $media->getPath();
        abort_unless(is_file($path), 404);

        if ($media->collection_name !== 'default') {
            abort_unless($media->collection_name === EpisodeAssembly::UPSCALED, 404);
            $checksum = hash_file('sha256', $path);
            abort_unless($checksum !== false && $this->files->hasReadyUpscale($prompt, $media->id, $checksum), 404);
        }

        return $path;
    }
}
