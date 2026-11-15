<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Data\Series\SeriesData;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Validation\ValidationException;

class SaveSeries
{
    public function run(Project $project, SeriesData $data, ?Series $series = null): Series
    {
        if ($series instanceof Series) {
            $series = Series::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->findOrFail($series->id);

            if ($data->lock_version !== $series->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'The series was changed. Refresh it before saving.']);
            }

            if ($series->archived_at !== null) {
                throw ValidationException::withMessages(['series' => 'An archived series cannot be changed.']);
            }
        }

        $series ??= new Series(['project_id' => $project->id]);
        $series->fill(['name' => $data->name, 'specification' => $data->specification->toArray()]);

        if ($series->exists && $series->isDirty(['name', 'specification'])) {
            $series->lock_version++;

            if ($series->isDirty('specification')) {
                $series->specification_version++;
            }
        }

        $series->save();

        return $series->refresh();
    }
}
