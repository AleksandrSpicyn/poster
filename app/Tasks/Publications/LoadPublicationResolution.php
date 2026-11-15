<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Data\PublishVideoData;
use App\Enums\PublicationStatus;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PublicationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LoadPublicationResolution
{
    /** @return array{request: PublicationRequest, channel: Channel, video: PublishVideoData}|null */
    public function run(PublicationRequest $request, User $actor, string $externalId, bool $lock = false): ?array
    {
        $actor = $actor->fresh();

        if ($actor === null) {
            throw new AuthorizationException();
        }

        Gate::forUser($actor)->authorize('manageSeries');

        $query = PublicationRequest::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        $request = $query->findOrFail($request->id);
        $channelQuery = $request->channel();

        if ($lock) {
            $channelQuery->lockForUpdate();
        }

        $channel = $channelQuery->firstOrFail();
        $assembly = $request->assembly()->firstOrFail();
        $series = $assembly->episodeRevision()->firstOrFail()->episode()->firstOrFail()->series()->firstOrFail();

        if (! User::query()->whereKey($actor->id)->whereNotNull('email_verified_at')->exists()
            || $channel->project_id !== $series->project_id) {
            throw ValidationException::withMessages(['publication' => 'A verified operator and a channel from this project are required.']);
        }

        if ($request->status === PublicationStatus::PUBLISHED
            && Post::query()->where('publication_request_id', $request->id)->where('external_id', $externalId)->exists()) {
            return null;
        }

        if ($request->status !== PublicationStatus::SUBMISSION_UNKNOWN) {
            throw ValidationException::withMessages(['publication' => 'Only an unknown external publication can be confirmed by its receipt.']);
        }

        if (Post::query()->where('channel_id', $channel->id)->where('external_id', $externalId)
            ->where(fn($query) => $query->whereNull('publication_request_id')->orWhere('publication_request_id', '!=', $request->id))
            ->exists()) {
            throw ValidationException::withMessages(['external_id' => 'This external video is already linked to another publication.']);
        }

        return ['request' => $request, 'channel' => $channel, 'video' => new PublishVideoData(
            '',
            '',
            $request->payload['title'],
            $request->payload['description'],
            $assembly->id,
            $request->id,
            $request->payload['made_for_kids'],
        )];
    }
}
