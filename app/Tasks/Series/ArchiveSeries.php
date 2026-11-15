<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Models\Series;
use Illuminate\Validation\ValidationException;

class ArchiveSeries
{
    public function run(Series $series, int $expectedVersion): void
    {
        $series = Series::query()->lockForUpdate()->findOrFail($series->id);

        if ($series->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'This record was changed. Refresh it before archiving.']);
        }

        if ($series->archived_at !== null) {
            return;
        }

        $series->update(['archived_at' => now(), 'lock_version' => $series->lock_version + 1]);
    }
}
