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

    public function run(PublicationRequest $request, PublicationStatus $expected, PublicationStatus $status, ?string $containerId = null, ?string $error = null): bool
    {
        $updated = $this->publications->transitionClaim($request, $expected, $status, $containerId, $error);

        if ($updated === 0) {
            return false;
        }

        WorkspaceUpdated::forResource($request);

        return true;
    }
}
