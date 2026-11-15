<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Data\Episodes\PublishEpisodeData;
use App\Enums\PublicationStatus;
use App\Jobs\PublishEpisodeJob;
use App\Models\EpisodeAssembly;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Tasks\Publications\CreatePublicationRequest;
use Illuminate\Database\Eloquent\Collection;

readonly class RequestEpisodePublicationAction
{
    public function __construct(private CreatePublicationRequest $create) {}

    /** @return Collection<int, PublicationRequest> */
    public function run(EpisodeAssembly $assembly, PublishEpisodeData $data, User $actor): Collection
    {
        $requests = $this->create->run($assembly, $data, $actor);

        foreach ($requests as $request) {
            if ($request->status === PublicationStatus::PLANNED && $request->wasRecentlyCreated) {
                PublishEpisodeJob::dispatch($request->id)->afterCommit();
            }
        }

        return $requests;
    }
}
