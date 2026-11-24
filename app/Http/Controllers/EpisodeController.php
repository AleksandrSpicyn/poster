<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

abstract class EpisodeController
{
    protected function redirectToEpisode(Series $series, Episode $episode): RedirectResponse
    {
        return to_route('episodes.show', ['series' => $series, 'episode' => $episode]);
    }
}
