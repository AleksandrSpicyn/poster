<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Dashboard\CollectPublicationMetricsAction;
use App\Enums\MetricCollectionStatus;
use App\Models\User;
use App\Repositories\PublicationObservationRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class CollectPublicationMetrics extends Command
{
    protected $signature = 'publications:metrics';

    protected $description = 'Record daily metrics for published Poster videos using their own channel authorizations';

    public function handle(PublicationObservationRepository $observations, CollectPublicationMetricsAction $action): int
    {
        $status = self::SUCCESS;

        foreach ($observations->channels() as $channel) {
            $owner = $channel->owner;

            if (! $owner instanceof User) {
                continue;
            }

            try {
                $count = $action->run($owner, $channel);
                $this->info("Channel {$channel->id}: $count observations.");
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                $denied = $exception instanceof RequestException && in_array($exception->response->status(), [401, 403], true);
                $observations->mark($channel, $denied ? MetricCollectionStatus::NO_PERMISSION : MetricCollectionStatus::TEMPORARY_ERROR);
                $status = self::FAILURE;
                $this->error("Channel {$channel->id}: could not collect metrics. Check authorization and provider availability.");
            }
        }

        return $status;
    }
}
