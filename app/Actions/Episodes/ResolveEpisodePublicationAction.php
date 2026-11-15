<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Classes\Story\Snapshot;
use App\Data\PostData;
use App\Exceptions\PublicationVerificationException;
use App\Models\Channel;
use App\Models\PublicationRequest;
use App\Models\User;
use App\Tasks\Posts\SavePost;
use App\Tasks\Publications\LoadPublicationResolution;
use App\Tasks\Publications\VerifyPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class ResolveEpisodePublicationAction
{
    public function __construct(
        private LoadPublicationResolution $load,
        private VerifyPublication $verify,
        private SavePost $savePost,
    ) {}

    public function run(PublicationRequest $request, User $actor, string $externalId): void
    {
        if (strlen($externalId) > 128 || preg_match('/\A[A-Za-z0-9_-]+\z/', $externalId) !== 1) {
            throw ValidationException::withMessages(['external_id' => 'Enter an external video ID rather than a URL.']);
        }

        $context = $this->load->run($request, $actor, $externalId);

        if ($context === null) {
            return;
        }

        try {
            $verified = $this->verify->run($context['channel'], $context['video'], $externalId);
        } catch (PublicationVerificationException $exception) {
            throw ValidationException::withMessages(['external_id' => $exception->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['external_id' => 'The provider could not verify this video. Check the video ID and account read permissions before trying again.']);
        }

        $fingerprint = $this->channelFingerprint($context['channel']);
        $payloadHash = Snapshot::hash($context['request']->payload);

        DB::transaction(function () use ($request, $actor, $externalId, $verified, $fingerprint, $payloadHash): void {
            $current = $this->load->run($request, $actor, $externalId, lock: true);

            if ($current === null) {
                return;
            }

            if ($fingerprint !== $this->channelFingerprint($current['channel']) || $payloadHash !== Snapshot::hash($current['request']->payload)) {
                throw ValidationException::withMessages(['publication' => 'The publication or account changed during verification. Verify it again.']);
            }

            $video = $current['video'];
            $this->savePost->run(new PostData(
                $current['channel'],
                null,
                $video->title,
                $video->description,
                $verified->url,
                $verified->externalId,
                $video->episodeAssemblyId,
                $video->publicationRequestId,
            ));
        });
    }

    private function channelFingerprint(Channel $channel): string
    {
        return Snapshot::hash(['project_id' => $channel->project_id, 'type' => $channel->type->value,
            'client_id' => $channel->client_id, 'credentials' => $channel->credentials]);
    }
}
