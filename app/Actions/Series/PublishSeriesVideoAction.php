<?php

declare(strict_types=1);

namespace App\Actions\Series;

use App\Data\Episodes\PublishEpisodeData;
use App\Enums\PublicationStatus;
use App\Jobs\PublishEpisodeJob;
use App\Models\SeriesAssembly;
use App\Models\User;
use App\Tasks\Publications\CreateSeriesPublication;

readonly class PublishSeriesVideoAction
{
    public function __construct(private CreateSeriesPublication $create) {}

    public function run(SeriesAssembly $assembly, PublishEpisodeData $data, User $actor): void
    {
        foreach ($this->create->run($assembly, $data, $actor) as $request) {
            if ($request->wasRecentlyCreated && $request->status === PublicationStatus::PLANNED) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            }
        }
    }
}
