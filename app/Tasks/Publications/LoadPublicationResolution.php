<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\Publications\PreparedPublicationData;
use App\Data\PublishVideoData;
use App\Enums\PublicationStatus;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Repositories\ChannelRepository;
use App\Repositories\PostRepository;
use App\Repositories\PublicationRequestRepository;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LoadPublicationResolution
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ChannelRepository $channels,
        private readonly PublicationRequestRepository $publications,
        private readonly PostRepository $posts,
    ) {}

    public function run(PublicationRequest $request, User $actor, string $externalId, bool $lock = false): ?PreparedPublicationData
    {
        $actor = $this->users->fresh($actor);

        if (! $actor instanceof User) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        $context = $this->publications->resolutionContext($request, $lock);
        $request = $context->request;
        $channel = $context->channel;
        $assembly = $context->assembly;
        $series = $context->series;

        if (! $this->users->isVerified($actor->id) || ! $this->channels->belongsToProject($channel, $series->project_id)) {
            throw ValidationException::withMessages(['publication' => 'A verified operator and a channel from this project are required.']);
        }

        if ($request->status === PublicationStatus::PUBLISHED
            && $this->posts->hasReceipt($request, $externalId)) {
            return null;
        }

        if ($request->status !== PublicationStatus::SUBMISSION_UNKNOWN) {
            throw ValidationException::withMessages(['publication' => 'Only an unknown external publication can be confirmed by its receipt.']);
        }

        if ($this->posts->receiptUsedElsewhere($channel, $request, $externalId)) {
            throw ValidationException::withMessages(['external_id' => 'This external video is already linked to another publication.']);
        }

        return new PreparedPublicationData($channel, new PublishVideoData(
            '',
            '',
            $request->payload['title'],
            $request->payload['description'],
            $assembly->id,
            $request->id,
            $request->payload['made_for_kids'],
        ), $request);
    }
}
