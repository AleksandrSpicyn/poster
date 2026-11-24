<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Data\Publications\PublicationResolutionData;
use App\Enums\PublicationStatus;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Tasks\Publications\LoadPublicationResolution;

final readonly class GetPublicationReviewAction
{
    public function __construct(private LoadPublicationResolution $resolution) {}

    public function run(PublicationRequest $publication, User $owner): PublicationResolutionData
    {
        $context = $this->resolution->context($publication, $owner);
        abort_unless($context->request->status === PublicationStatus::SUBMISSION_UNKNOWN && $context->channel->canVerifyPublication(), 404);

        return $context;
    }
}
