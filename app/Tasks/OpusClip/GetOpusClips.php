<?php

declare(strict_types=1);

namespace App\Tasks\OpusClip;

use App\Data\GetPromptsListData;
use App\Data\OpusClip\GamingTrendReport;
use App\Data\OpusClip\WeeklyClipViewsData;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use App\Repositories\OpusMetricRepository;
use App\Repositories\OpusTrendRepository;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class GetOpusClips
{
    public function __construct(private OpusClipRepository $clips, private OpusTrendRepository $reports, private OpusMetricRepository $metrics) {}

    /** @return list<GamingTrendReport> */
    public function trends(Project $project): array
    {
        return $this->reports->trends($project);
    }

    public function week(Project $project, string $date): WeeklyClipViewsData
    {
        $start = CarbonImmutable::parse($date, 'UTC')->startOfWeek();
        $end = $start->endOfWeek()->min(CarbonImmutable::today('UTC'))->toDateString();
        $baseline = $start->subDay()->toDateString();
        $rows = $this->metrics->clipWeek($project, $baseline, $end);
        $totals = [];
        $observed = null;

        foreach ($rows->groupBy(fn(OpusClipMetric $row): string => serialize([$row->platform, $row->account_id, $row->media_id])) as $samples) {
            $values = $samples->filter(fn(OpusClipMetric $item): bool => isset($item->metrics['views']));
            $first = $values->first();
            $last = $values->last();

            if (! $first instanceof OpusClipMetric || ! $last instanceof OpusClipMetric || $last->observed_on === $baseline) {
                continue;
            }

            $observed = max($observed, $last->observed_on);

            if ($first->observed_on !== $baseline || $last->observed_on !== $end) {
                continue;
            }

            $total = $totals[$last->opus_clip_id] ?? ['title' => $last->clip_title ?? 'Клип без названия', 'views' => 0];
            $total['views'] += $last->metrics['views'] - $first->metrics['views'];
            $totals[$last->opus_clip_id] = $total;
        }

        return new WeeklyClipViewsData($start->toDateString(), $end, $observed, array_values(collect($totals)->sortByDesc('views')->all()));
    }

    /** @return LengthAwarePaginator<int, OpusClip> */
    public function run(Project $project, GetPromptsListData $filters): LengthAwarePaginator
    {
        return $this->clips->paginate($project, $filters->perPage ?? 10, $filters->page ?? 1, $filters->updated);
    }
}
