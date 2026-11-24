<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Models\EpisodeAssembly;
use App\Tasks\Episodes\GetEpisodeExport;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetEpisodeExportAction
{
    public function __construct(private GetEpisodeExport $getMedia) {}

    public function run(EpisodeAssembly $assembly, Media $media, ?string $checksum = null): string
    {
        return $this->getMedia->run($assembly, $media, $checksum);
    }
}
