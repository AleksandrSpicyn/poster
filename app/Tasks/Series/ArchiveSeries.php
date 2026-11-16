<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Series;
use App\Repositories\SeriesRepository;
use Illuminate\Validation\ValidationException;

class ArchiveSeries
{
    public function __construct(private readonly SeriesRepository $series) {}

    public function run(Series $series, int $expectedVersion): void
    {
        $series = $this->series->findLockedOrFail($series->id);

        if ($series->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'This record was changed. Refresh it before archiving.']);
        }

        if ($series->archived_at !== null) {
            return;
        }

        $this->series->update($series, ['archived_at' => now(), 'lock_version' => $series->lock_version + 1]);
    }
}
