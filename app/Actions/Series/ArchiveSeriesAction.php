<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Models\Series;
use App\Tasks\Series\ArchiveSeries;
use Illuminate\Support\Facades\DB;

readonly class ArchiveSeriesAction
{
    public function __construct(private ArchiveSeries $archive) {}

    public function run(Series $series, int $expectedVersion): void
    {
        DB::transaction(fn() => $this->archive->run($series, $expectedVersion));
    }
}
