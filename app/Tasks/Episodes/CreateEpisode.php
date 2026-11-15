<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\CreateEpisodeData;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEpisode
{
    public function run(Series $series, CreateEpisodeData $data): Episode
    {
        return DB::transaction(function () use ($series, $data): Episode {
            $series = Series::query()->lockForUpdate()->findOrFail($series->id);

            if ($series->archived_at !== null) {
                throw ValidationException::withMessages(['series' => 'An archived series cannot receive new episodes.']);
            }

            $previous = $series->episodes()->orderByDesc('number')->first();

            return $series->episodes()->create([
                'title' => $data->title,
                'number' => ($previous->number ?? 0) + 1,
                'predecessor_episode_id' => $previous?->id,
            ])->refresh();
        });
    }
}
