<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\AcceptSeriesVideoData;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Series\AcceptSeriesAssembly;
use Illuminate\Support\Facades\Gate;

readonly class ReviewSeriesVideoAction
{
    public function __construct(private AcceptSeriesAssembly $accept) {}

    public function run(SeriesAssembly $assembly, AcceptSeriesVideoData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('manageSeries');
        $this->accept->run($assembly, $data, $actor);
    }
}
