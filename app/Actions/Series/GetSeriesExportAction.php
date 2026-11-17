<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\SeriesAssembly;
use App\Tasks\Series\GetSeriesExport;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetSeriesExportAction
{
    public function __construct(private GetSeriesExport $get) {}

    public function run(SeriesAssembly $assembly, Media $media, bool $preview = false): string
    {
        return $this->get->run($assembly, $media, $preview);
    }
}
