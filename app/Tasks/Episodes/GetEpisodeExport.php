<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Enums\AssemblyStatus;
use App\Enums\EpisodeValidity;
use App\Models\EpisodeAssembly;
use App\Repositories\EpisodeAssemblyRepository;
use App\Repositories\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GetEpisodeExport
{
    public function __construct(private readonly MediaRepository $media, private readonly EpisodeAssemblyRepository $assemblies) {}

    public function run(EpisodeAssembly $assembly, Media $media, ?string $checksum = null): string
    {
        $this->assemblies->forExport($assembly);
        $episode = $assembly->episodeRevision?->episode;

        abort_unless($assembly->status === AssemblyStatus::ACCEPTED
            && $episode?->accepted_assembly_id === $assembly->id
            && $episode->validity === EpisodeValidity::CURRENT
            && $media->model_type === $assembly->getMorphClass()
            && $media->model_id === $assembly->id
            && ($checksum === null ? $media->collection_name === EpisodeAssembly::VIDEO : $this->media->episodeExport($assembly, $media->id, $checksum) instanceof Media)
            && $media->disk === 'local', 404);

        $path = $media->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }
}
