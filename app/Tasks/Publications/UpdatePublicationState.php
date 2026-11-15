<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Enums\PublicationStatus;
use App\Models\PublicationRequest;

class UpdatePublicationState
{
    public function run(PublicationRequest $request, PublicationStatus $status, ?string $containerId = null, ?string $error = null): void
    {
        PublicationRequest::query()->whereKey($request->id)
            ->where('status', '!=', PublicationStatus::PUBLISHED)
            ->update(['status' => $status, 'container_id' => $containerId ?? $request->container_id, 'error_message' => $error]);
    }
}
