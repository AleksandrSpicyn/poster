<?php

declare(strict_types=1);

namespace App\Actions\Episodes;

use App\Enums\PublicationStatus;
use App\Jobs\FinishEpisodePublicationJob;
use App\Models\PublicationRequest;
use App\Tasks\Publications\ClaimPublication;
use App\Tasks\Publications\PrepareEpisodePublication;
use App\Tasks\Publications\StartPublication;
use App\Tasks\Publications\UpdatePublicationState;
use Throwable;

readonly class SubmitEpisodePublicationAction
{
    public function __construct(
        private ClaimPublication $claim,
        private PrepareEpisodePublication $prepare,
        private StartPublication $send,
        private UpdatePublicationState $update,
    ) {}

    public function run(PublicationRequest $request): void
    {
        if (! $this->claim->run($request)) {
            return;
        }

        try {
            $context = $this->prepare->run($request);
            $result = $this->send->run($context['channel'], $context['video']);

            if ($result->containerId !== null) {
                $this->update->run($request, PublicationStatus::PROCESSING, $result->containerId);
                FinishEpisodePublicationJob::dispatch($request->id)->delay(now()->addMinutes(5))->afterCommit();

                return;
            }

            if (! $result->accepted) {
                $this->update->run($request, PublicationStatus::SUBMISSION_UNKNOWN, error: 'The provider did not confirm publication. Verify its outcome before trying again.');
            }
        } catch (Throwable $exception) {
            $this->update->run($request, PublicationStatus::SUBMISSION_UNKNOWN, error: 'The publication outcome is unknown. Inspect the provider receipt before any retry.');
            report($exception);
        }
    }
}
