<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\OpusClip\GamingTrendReport;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Enums\ChannelType;
use App\Enums\ProductionStatus;
use App\Models\Channel;
use App\Models\OpusClip;
use App\Models\OpusClipMetric;
use App\Models\OpusGameTrend;
use App\Models\OpusSource;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;

/** @extends EloquentRepository<OpusClip> */
final readonly class OpusClipRepository extends EloquentRepository
{
    protected function model(): string
    {
        return OpusClip::class;
    }

    public function project(): ?Project
    {
        return Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->first();
    }

    public function configure(int $projectId, ?string $youtubeAccount, ?string $instagramAccount, ?string $tiktokAccount = null): void
    {
        DB::transaction(function () use ($projectId, $youtubeAccount, $instagramAccount, $tiktokAccount): void {
            $project = Project::query()->lockForUpdate()->find($projectId);

            if (! $project instanceof Project) {
                throw new RuntimeException('Poster project not found.');
            }

            if (Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->whereKeyNot($projectId)->exists()) {
                throw new RuntimeException('The OpusClip workspace is already assigned to another Poster project.');
            }

            if ($project->productionRuns()->whereIn('status', ProductionStatus::blockingValues())->exists()) {
                throw new RuntimeException('Resolve the existing production run before configuring clipping.');
            }

            $channels = $project->channels()->orderBy('channels.id')->lockForUpdate()->get();

            foreach ([ChannelType::YOUTUBE, ChannelType::INSTAGRAM, ChannelType::TIKTOK] as $type) {
                $matches = $channels->filter(fn(Channel $channel): bool => $channel->type === $type);

                if ($type === ChannelType::TIKTOK && $matches->isEmpty() && $tiktokAccount === null) {
                    continue;
                }

                if ($matches->count() !== 1) {
                    throw new RuntimeException('Attach exactly one ' . $type->label() . ' channel to the project.');
                }

                $channel = $matches->sole();

                if ($channel->projects()->whereKeyNot($projectId)->exists()) {
                    throw new RuntimeException('Attach a dedicated channel for each clipping account.');
                }

                $credential = $type === ChannelType::INSTAGRAM ? 'account_name' : 'channel_id';
                $connected = $channel->credentials[$credential] ?? null;
                $account = (match ($type) {
                    ChannelType::YOUTUBE => $youtubeAccount, ChannelType::INSTAGRAM => $instagramAccount, default => $tiktokAccount,
                }) ?? $channel->external_account ?? $connected;

                if (! is_string($account) || trim($account) === '' || mb_strlen($account) > 255) {
                    throw new RuntimeException('Supply the external account for the ' . $type->label() . ' channel or connect it in Poster.');
                }

                if (($channel->external_account !== null && $channel->external_account !== $account)
                    || ($connected !== null && $connected !== $account)) {
                    throw new RuntimeException('The supplied account differs from the existing channel identity.');
                }

                $channel->external_account = $account;
                $channel->save();
            }

            $project->clipping_workspace = Project::OPUS_CLIP_WORKSPACE;
            $project->save();
        }, attempts: 3);
    }

    /** @param iterable<ImportedClipData> $clips */
    public function import(OpusSourceData $source, iterable $clips): Project
    {
        return DB::transaction(function () use ($source, $clips): Project {
            $project = Project::query()->where('clipping_workspace', Project::OPUS_CLIP_WORKSPACE)->lockForUpdate()->first();

            if (! $project instanceof Project) {
                throw new RuntimeException('Select a Poster project with opus:configure before importing clips.');
            }

            $origin = $project->opusSources()->firstOrNew(['external_id' => $source->id]);
            $origin->title = $source->title ?? $origin->title;
            $origin->game = $source->game ?? $origin->game;
            $origin->save();

            foreach ($clips as $clip) {
                $record = $this->query()->firstOrNew(['opus_source_id' => $origin->id, 'clip_id' => $clip->id]);
                $oldLinks = collect($record->publications ?? [])
                    ->filter(fn(array $post): bool => $post['url'] !== null)
                    ->whereNotIn('url', array_column($clip->publications, 'url'));

                $record->fill([
                    'title' => $clip->title ?? $record->title,
                    'duration_ms' => $clip->durationMs ?? $record->duration_ms,
                    'score' => $clip->score ?? $record->score,
                    'publications' => [...$clip->publications, ...$oldLinks->values()->all()],
                ])->save();
            }

            return $project;
        }, attempts: 3);
    }

    /** @return LengthAwarePaginator<int, OpusClip> */
    public function paginate(Project $project, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->query()->whereRelation('source', 'project_id', $project->id)->with(['source','latestMetrics'])
            ->orderByDesc('id')->paginate($perPage, page: $page)->withQueryString();
    }

    /** @return LazyCollection<int, OpusClip> */
    public function forMetrics(Project $project): LazyCollection
    {
        return $this->query()->whereRelation('source', 'project_id', $project->id)->lazyById();
    }

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

    /** @return Collection<int, Channel> */
    public function channels(Project $project): Collection
    {
        return $project->channels()->whereNotNull('external_account')->where('channels.is_active', true)->get();
    }

    /** @return Collection<int, OpusClipMetric> */
    public function observations(Project $project, string $day): Collection
    {
        return OpusClipMetric::query()->whereRelation('clip.source', 'project_id', $project->id)
            ->where('observed_on', $day)->with('clip.source')->get();
    }

    /** @param array<string, int|float> $metrics */
    public function observe(OpusClip $clip, ChannelType $platform, string $mediaId, array $metrics): void
    {
        OpusClipMetric::query()->updateOrCreate([
            'opus_clip_id' => $clip->id, 'platform' => $platform->value, 'media_id' => $mediaId,
            'observed_on' => now('UTC')->toDateString(),
        ], ['metrics' => $metrics]);
    }
}
