<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\OpusClip\GamingTrendReport;
use App\Models\OpusGameTrend;
use App\Models\OpusSource;
use App\Models\Project;

final readonly class OpusTrendRepository
{
    /** @return list<string> */
    public function games(Project $project): array
    {
        $games = OpusSource::query()->where('project_id', $project->id)->whereNotNull('game')
            ->select('game')->union(OpusGameTrend::query()->where('project_id', $project->id)->select('game'))
            ->orderBy('game')->pluck('game')->all();

        return array_values(array_filter($games, is_string(...)));
    }

    public function saveTrends(Project $project, GamingTrendReport $report): void
    {
        OpusGameTrend::query()->updateOrCreate(['project_id' => $project->id, 'game' => mb_substr($report->game, 0, 255)], [
            'entries' => $report->entries, 'updated_at' => now(),
        ]);
    }

    /** @return list<GamingTrendReport> */
    public function trends(Project $project): array
    {
        return array_values(OpusGameTrend::query()->where('project_id', $project->id)
            ->where('updated_at', '>=', now()->subWeek())->orderBy('game')->get()
            ->map(fn(OpusGameTrend $row): GamingTrendReport => new GamingTrendReport($row->game, $row->entries, $row->updated_at?->toIso8601String()))
            ->all());
    }
}
