<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Enums\PublicationStatus;
use App\Exceptions\PublicationProcessingException;
use App\Exceptions\PublicationRejectedException;
use App\Models\PublicationRequest;
use App\Tasks\Publications\ClaimPublication;
use App\Tasks\Publications\FinishPublication;
use App\Tasks\Publications\PrepareEpisodePublication;
use App\Tasks\Publications\UpdatePublicationState;
use Throwable;

readonly class FinishEpisodePublicationAction
{
    public function __construct(
        private ClaimPublication $claim,
        private PrepareEpisodePublication $prepare,
        private FinishPublication $publish,
        private UpdatePublicationState $update,
    ) {}

    public function run(PublicationRequest $request): void
    {
        if (! $this->claim->run($request, container: true)) {
            return;
        }

        try {
            $context = $this->prepare->run($request);
            $this->publish->run($context->channel, $context->video, $request->container_id ?? '');
        } catch (PublicationProcessingException $exception) {
            $this->update->run($request, $exception->inbox ? PublicationStatus::INBOX : PublicationStatus::PROCESSING, error: $exception->getMessage());
        } catch (PublicationRejectedException $exception) {
            $this->update->run($request, PublicationStatus::FAILED, error: $exception->getMessage());
        } catch (Throwable $exception) {
            $this->update->run($request, PublicationStatus::SUBMISSION_UNKNOWN, error: 'Publishing the prepared container was not confirmed. Verify its outcome before retrying.');
            report($exception);
        }
    }
}
