<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\OpusClip\SyncOpusMetricsAction;
use App\Enums\ChannelType;
use App\Enums\MetricCollectionStatus;
use App\Models\Channel;
use App\Models\Project;
use App\Repositories\OpusWorkspaceRepository;
use App\Repositories\PublicationObservationRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class SyncOpusMetrics extends Command
{
    protected $signature = 'opus:metrics';

    protected $description = 'Record daily owner analytics for linked OpusClip publications';

    public function handle(OpusWorkspaceRepository $workspace, SyncOpusMetricsAction $action, PublicationObservationRepository $observations): int
    {
        $project = $workspace->project();

        if (! $project instanceof Project) {
            $this->error('Import clips with opus:sync first.');

            return self::FAILURE;
        }

        $result = self::SUCCESS;

        $channels = $workspace->channels($project);

        if ($channels->isEmpty()) {
            return $result;
        }

        $platforms = $channels->map(fn(Channel $channel): ChannelType => $channel->type)->unique()->all();
        $targets = $action->targets($project, array_values($platforms));

        foreach ($channels as $channel) {
            try {
                $count = $action->run($project, $channel, $targets[$channel->type->value] ?? []);
                $observations->mark($channel, $count > 0 ? MetricCollectionStatus::COLLECTED : MetricCollectionStatus::NO_DATA);
                $this->info($channel->type->label() . ": $count observations.");
            } catch (RequestException $error) {
                $observations->mark($channel, match ($error->response->status()) {
                    401, 403 => MetricCollectionStatus::NO_PERMISSION,
                    429 => MetricCollectionStatus::RATE_LIMITED,
                    default => MetricCollectionStatus::TEMPORARY_ERROR,
                });
                $this->error($channel->type->label() . ': provider returned HTTP ' . $error->response->status() . '. Check the channel authorization.');
                $result = self::FAILURE;
            } catch (ConnectionException|RuntimeException) {
                $observations->mark($channel, MetricCollectionStatus::TEMPORARY_ERROR);
                $this->error($channel->type->label() . ': could not read analytics. Check account, connection and permissions.');
                $result = self::FAILURE;
            }
        }

        return $result;
    }
}
