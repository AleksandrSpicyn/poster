<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChannelType;
use App\Models\Project;
use App\Repositories\OpusMetricRepository;
use App\Repositories\OpusWorkspaceRepository;
use Illuminate\Console\Command;

class ReportClipPerformance extends Command
{
    protected $signature = 'opus:performance {--days=7 : Days between the two UTC observations}';

    protected $description = 'Rank gaming publications on each platform by observed growth in views';

    public function handle(OpusWorkspaceRepository $workspace, OpusMetricRepository $observations): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        if ($days === false) {
            $this->error('Choose a window between 1 and 365 days.');

            return self::INVALID;
        }

        $project = $workspace->project();

        if (! $project instanceof Project) {
            $this->error('Select the clipping project with opus:configure.');

            return self::FAILURE;
        }

        $today = now('UTC');
        $start = $today->copy()->subDays($days)->toDateString();
        $end = $today->toDateString();
        $ranked = $observations->topViewGrowth($project, $start, $end)->groupBy('platform');
        $this->info("Observed view growth, $start to $end (UTC)");

        $platforms = [ChannelType::YOUTUBE, ChannelType::INSTAGRAM];

        if ($ranked->has(ChannelType::TIKTOK->value)) {
            $platforms[] = ChannelType::TIKTOK;
        }

        foreach ($platforms as $platform) {
            $rows = [];

            foreach ($ranked->get($platform->value) ?? [] as $row) {
                if ($row->growth !== null) {
                    $rows[] = [
                        $row->game ?? 'Unassigned',
                        $row->clip_title,
                        $row->growth,
                        $row->views,
                    ];
                }
            }

            $this->info($platform->label());

            if ($rows === []) {
                $this->line('No publications with view counts on both dates.');

                continue;
            }

            $this->table([
                'Game',
                'Clip',
                'View change',
                'Total views',
            ], $rows);
        }

        return self::SUCCESS;
    }
}
