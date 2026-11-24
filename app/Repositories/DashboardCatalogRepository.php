<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\DashboardProductionData;
use App\Models\Channel;
use App\Models\Episode;
use App\Models\Post;
use App\Models\Project;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class DashboardCatalogRepository
{
    /** @return Collection<int, Project> */
    public function projects(User $owner): Collection
    {
        return Project::query()->where('owner_id', $owner->id)->whereNull('opus_source_id')
            ->with('media', 'coverFrame')->orderBy('name')->get();
    }

    /** @return Collection<int, Channel> */
    public function channels(User $owner): Collection
    {
        return Channel::query()->where('owner_id', $owner->id)
            ->with(['projects' => fn($query) => $query->where('projects.owner_id', $owner->id)->select('projects.id')])
            ->orderBy('name')->get([
                'id',
                'name',
                'type',
                'credentials',
                'external_account',
            ]);
    }

    /**
     * @param list<int> $projects
     * @param list<int>|null $channelIds
     */
    public function production(User $owner, array $projects, ?array $channelIds): DashboardProductionData
    {
        $episodes = Episode::query()->whereNull('accepted_assembly_id')
            ->whereHas('series', fn(Builder $series) => $series->whereNull('archived_at')->whereIn('project_id', $projects))->count();
        $prompt = Prompt::query()->whereIn('project_id', $projects)->whereNull('shot_id')->latest()->latest('id')->first();
        $post = Post::query()->where(fn(Builder $query) => $query->whereNull('channel_id')->orWhereRelation('channel', 'owner_id', $owner->id))
            ->when($channelIds !== null, fn(Builder $query) => $query->whereIn('channel_id', $channelIds ?? []))
            ->where(fn(Builder $query) => $query->whereHas('prompt', fn(Builder $prompt) => $prompt->whereIn('project_id', $projects))
                ->orWhereHas('episodeAssembly.episodeRevision.episode.series', fn(Builder $series) => $series->whereIn('project_id', $projects))
                ->orWhereHas('seriesAssembly.series', fn(Builder $series) => $series->whereIn('project_id', $projects)))
            ->latest()->latest('id')->first(['title']);

        return new DashboardProductionData(count($projects), $episodes, $prompt?->prompt, $prompt === null ? null : ($prompt->generated ? 'готово' : 'в работе'), $post?->title);
    }
}
