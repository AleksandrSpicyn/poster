<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Enums\PublicationStatus;
use App\Events\WorkspaceUpdated;
use App\Models\PublicationRequest;
use App\Repositories\PublicationRequestRepository;

class UpdatePublicationState
{
    public function __construct(private readonly PublicationRequestRepository $publications) {}

    public function run(PublicationRequest $request, PublicationStatus $status, ?string $containerId = null, ?string $error = null): void
    {
        $updated = $this->publications->updateUnpublished($request, $status, $containerId, $error);

        if ($updated > 0) {
            WorkspaceUpdated::dispatch(true);
        }
    }
}
