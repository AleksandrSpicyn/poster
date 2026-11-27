<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\SeriesAssetData;
use App\Models\Series;
use App\Tasks\Series\StoreSeriesAsset;

readonly class StoreSeriesAssetAction
{
    public function __construct(private StoreSeriesAsset $store) {}

    public function run(Series $series, SeriesAssetData $data): void
    {
        $this->store->run($series, $data);
    }
}
