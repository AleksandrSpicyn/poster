<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Enums\AssemblyStatus;
use App\Models\EpisodeAssembly;
use App\Models\SeriesAssembly;
use App\Repositories\MediaRepository;
use App\Repositories\SeriesAssemblyRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetSeriesExport
{
    public function __construct(private SeriesAssemblyRepository $assemblies, private MediaRepository $files) {}

    public function run(SeriesAssembly $assembly, Media $media, bool $preview = false, ?string $checksum = null): string
    {
        if (! $preview) {
            $this->assemblies->loadSeries($assembly);
        }

        $storedChecksum = $media->getCustomProperty('checksum');

        abort_unless(is_string($storedChecksum) && ($checksum === null || $checksum === $storedChecksum)
            && ($preview || ($assembly->status === AssemblyStatus::ACCEPTED && $assembly->series->final_assembly_id === $assembly->id))
            && $media->model_type === $assembly->getMorphClass() && $media->model_id === $assembly->id
            && ($media->collection_name === SeriesAssembly::VIDEO || ($media->collection_name === EpisodeAssembly::UPSCALED && $this->files->hasReadyUpscale($assembly, $media->id, $storedChecksum))) && $media->disk === 'local'
            && is_file($media->getPath()) && hash_file('sha256', $media->getPath()) === $storedChecksum, 404);

        return $media->getPath();
    }
}
