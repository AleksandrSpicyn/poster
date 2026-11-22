<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Enums\AssemblyStatus;
use App\Models\SeriesAssembly;
use App\Repositories\SeriesAssemblyRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetSeriesExport
{
    public function __construct(private SeriesAssemblyRepository $assemblies) {}

    public function run(SeriesAssembly $assembly, Media $media, bool $preview = false): string
    {
        if (! $preview) {
            $this->assemblies->loadSeries($assembly);
        }

        abort_unless(($preview || ($assembly->status === AssemblyStatus::ACCEPTED && $assembly->series->final_assembly_id === $assembly->id))
            && $media->model_type === $assembly->getMorphClass() && $media->model_id === $assembly->id
            && $media->collection_name === SeriesAssembly::VIDEO && $media->disk === 'local'
            && is_file($media->getPath()) && hash_file('sha256', $media->getPath()) === $media->getCustomProperty('checksum'), 404);

        return $media->getPath();
    }
}
