<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Tasks\Episodes\GetEpisodeMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetEpisodeMediaAction
{
    public function __construct(private GetEpisodeMedia $getMedia) {}

    public function run(Media $media): string
    {
        return $this->getMedia->run($media);
    }
}
