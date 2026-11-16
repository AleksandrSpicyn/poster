<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\PromptRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GetEpisodeMedia
{
    public function __construct(
        private readonly PromptRepository $prompts,
        private readonly EpisodeAssemblyRepository $assemblies,
    ) {}

    public function run(Media $media): string
    {
        $allowed = match ($media->model_type) {
            new Prompt()->getMorphClass() => in_array($media->collection_name, [Prompt::SHOT_IMAGE, Prompt::SHOT_VIDEO], true)
                && $this->prompts->isShotTake($media->model_id),
            new EpisodeAssembly()->getMorphClass() => in_array($media->collection_name, [EpisodeAssembly::VIDEO, EpisodeAssembly::CONTINUATION_FRAME, EpisodeAssembly::CONTINUATION_TAIL], true)
                && $this->assemblies->exists($media->model_id),
            default => false,
        };
        abort_unless($allowed && $media->disk === 'local', 404);
        $path = $media->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }
}
