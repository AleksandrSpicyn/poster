<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Classes\Publications\PublicationReference;
use App\Classes\Story\Snapshot;
use App\Data\PostData;
use App\Data\Publications\PreparedPublicationData;
use App\Data\Publications\PublicationResolutionData;
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
        $reference = PublicationReference::parse($externalId);
        $context = $this->load->run($request, $actor, $reference);

        if (! $context instanceof PreparedPublicationData) {
            return;
        }

        try {
            $verified = $this->verify->run($context->channel, $context->video, $reference->value);
        } catch (PublicationVerificationException $exception) {
            throw ValidationException::withMessages(['external_id' => $exception->getMessage()]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['external_id' => 'Площадка не подтвердила видео. Проверьте ссылку и права чтения у подключённого аккаунта.']);
        }

        $fingerprint = $this->channelFingerprint($context->channel);
        $payloadHash = Snapshot::hash($context->request->payload);

        DB::transaction(function () use ($request, $actor, $verified, $fingerprint, $payloadHash): void {
            $current = $this->load->run($request, $actor, PublicationReference::parse($verified->externalId), lock: true);

            if (! $current instanceof PreparedPublicationData) {
                return;
            }

            if ($fingerprint !== $this->channelFingerprint($current->channel) || $payloadHash !== Snapshot::hash($current->request->payload)) {
                throw ValidationException::withMessages(['publication' => 'Данные публикации или канала изменились. Повторите проверку.']);
            }

            $video = $current->video;
            $this->savePost->run(new PostData(
                $current->channel,
                $video->short,
                $video->title,
                $video->description,
                $verified->url,
                $verified->externalId,
                $video->episodeAssemblyId,
                $video->publicationRequestId,
                $video->seriesAssemblyId,
            ));
        });
    }

    public function review(PublicationRequest $request, User $actor): PublicationResolutionData
    {
        return $this->load->context($request, $actor);
    }

    private function channelFingerprint(Channel $channel): string
    {
        return Snapshot::hash(['id' => $channel->id, 'type' => $channel->type->value,
            'client_id' => $channel->oauthClientId(), 'credentials' => $channel->credentials]);
    }
}
