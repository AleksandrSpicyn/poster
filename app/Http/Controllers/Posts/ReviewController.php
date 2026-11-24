<?php

declare(strict_types=1);

namespace App\Http\Controllers\Posts;

use App\Actions\Publications\GetPublicationReviewAction;
use App\Http\Resources\ProjectCardResource;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Arr;
use Inertia\Response;

class ReviewController
{
    public function __invoke(PublicationRequest $publication, #[CurrentUser] User $owner, GetPublicationReviewAction $action): Response
    {
        $context = $action->run($publication, $owner);

        return inertia('Posts/Review', [
            'id' => $context->request->id,
            'title' => $context->request->payload['title'],
            'channel' => $context->channel->name,
            'platform' => $context->channel->type->label(),
            'project' => Arr::only(ProjectCardResource::make($context->project)->resolve(), ['name', 'cover']),
            'assemblyNumber' => $context->assemblyNumber,
            'submittedAt' => $context->request->submitted_at?->toISOString(),
        ]);
    }
}
