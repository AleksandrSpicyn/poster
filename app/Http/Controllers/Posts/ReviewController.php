<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Episodes\ResolveEpisodePublicationAction;
use App\Enums\PublicationStatus;
use App\Http\Resources\ProjectResource;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Arr;
use Inertia\Response;

class ReviewController
{
    public function __invoke(PublicationRequest $publication, #[CurrentUser] User $owner, ResolveEpisodePublicationAction $action): Response
    {
        $context = $action->review($publication, $owner);
        abort_unless($context->request->status === PublicationStatus::SUBMISSION_UNKNOWN && $context->channel->canVerifyPublication(), 404);

        return inertia('Posts/Review', [
            'id' => $publication->id,
            'title' => $publication->payload['title'],
            'channel' => $context->channel->name,
            'platform' => $context->channel->type->label(),
            'project' => Arr::only(ProjectResource::make($context->project)->resolve(), ['name', 'cover']),
            'submittedAt' => $publication->submitted_at?->toISOString(),
        ]);
    }
}
