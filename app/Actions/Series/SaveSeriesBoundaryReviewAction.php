<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Series\SaveBoundaryReviewData;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Series\SaveSeriesBoundaryReview;

readonly class SaveSeriesBoundaryReviewAction
{
    public function __construct(private SaveSeriesBoundaryReview $save) {}

    public function run(SeriesAssembly $assembly, int $boundary, SaveBoundaryReviewData $data, User $actor): void
    {
        $this->save->run($assembly, $boundary, $data, $actor);
    }
}
