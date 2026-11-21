<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\OpusClip\SyncOpusMetricsAction;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class SyncOpusMetrics extends Command
{
    protected $signature = 'opus:metrics';

    protected $description = 'Record daily owner analytics for linked OpusClip publications';

    public function handle(OpusClipRepository $clips, SyncOpusMetricsAction $action): int
    {
        $project = $clips->project();

        if (! $project instanceof Project) {
            $this->error('Import clips with opus:sync first.');

            return self::FAILURE;
        }

        $result = self::SUCCESS;

        foreach ($clips->channels($project) as $channel) {
            try {
                $count = $action->run($project, $channel);
                $this->info($channel->type->label() . ": $count observations.");
            } catch (RequestException $error) {
                $this->error($channel->type->label() . ': provider returned HTTP ' . $error->response->status() . '. Check the channel authorization.');
                $result = self::FAILURE;
            } catch (ConnectionException|RuntimeException) {
                $this->error($channel->type->label() . ': could not read analytics. Check account, connection and permissions.');
                $result = self::FAILURE;
            }
        }

        return $result;
    }
}
