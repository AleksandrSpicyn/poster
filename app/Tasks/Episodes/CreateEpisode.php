<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\CreateEpisodeData;
use App\Enums\SeriesOutputMode;
use App\Models\Episode;
use App\Models\Series;
use App\Repositories\EpisodeRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEpisode
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly EpisodeRepository $episodes,
    ) {}

    public function run(Series $series, CreateEpisodeData $data): Episode
    {
        return DB::transaction(function () use ($series, $data): Episode {
            $series = $this->series->findLockedOrFail($series->id);

            if ($series->archived_at !== null) {
                throw ValidationException::withMessages(['series' => 'An archived series cannot receive new episodes.']);
            }

            $previous = $this->episodes->latestForSeries($series);

            if ($series->output_mode === SeriesOutputMode::SINGLE_VIDEO && ($series->cycle_plan === null || $series->episode_limit === null || ($previous->number ?? 0) >= $series->episode_limit)) {
                throw ValidationException::withMessages(['episode' => 'Prepare the finite cycle plan before creating its parts; the planned number cannot be exceeded.']);
            }

            return $this->episodes->createNext($series, $previous, $data->title);
        });
    }
}
