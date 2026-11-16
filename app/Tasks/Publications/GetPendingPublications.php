<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Events\WorkspaceUpdated;
use App\Models\PublicationRequest;
use App\Repositories\PublicationRequestRepository;
use Illuminate\Database\Eloquent\Collection;

class GetPendingPublications
{
    public function __construct(private readonly PublicationRequestRepository $publications) {}

    /** @return Collection<int, PublicationRequest> */
    public function run(int $limit): Collection
    {
        $limit = max(1, min(100, $limit));
        $updated = $this->publications->expireClaims($limit);

        if ($updated > 0) {
            WorkspaceUpdated::dispatch(true);
        }

        return $this->publications->pending($limit);
    }
}
