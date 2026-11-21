<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ChannelType;
use App\Models\OpusClipMetric;
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
        $key = static fn(OpusClipMetric $row): string => $row->opus_clip_id . ':' . $row->platform . ':' . $row->media_id;
        $baseline = $observations->observations($project, $start)->keyBy($key);
        $current = $observations->observations($project, $end);
        $this->info("Observed view growth, $start to $end (UTC)");

        $platforms = [ChannelType::YOUTUBE, ChannelType::INSTAGRAM];

        if ($current->contains('platform', ChannelType::TIKTOK->value) || $baseline->contains('platform', ChannelType::TIKTOK->value)) {
            $platforms[] = ChannelType::TIKTOK;
        }

        foreach ($platforms as $platform) {
            $rows = [];

            foreach ($current->where('platform', $platform->value) as $row) {
                $previous = $baseline->get($key($row))?->metrics['views'] ?? null;
                $views = $row->metrics['views'] ?? null;

                if ($previous !== null && $views !== null) {
                    $rows[] = [$row->clip->source->game ?? 'Unassigned', $row->clip->title ?? $row->clip->clip_id, $views - $previous, $views];
                }
            }

            $this->info($platform->label());

            if ($rows === []) {
                $this->line('No publications with view counts on both dates.');

                continue;
            }

            $this->table(['Game', 'Clip', 'View change', 'Total views'], collect($rows)->sortByDesc(2)->take(10)->values()->all());
        }

        return self::SUCCESS;
    }
}
