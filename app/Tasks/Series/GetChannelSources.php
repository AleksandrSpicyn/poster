<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Enums\ChannelType;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class GetChannelSources
{
    /** @return list<array{id: int, name: string}> */
    public function run(?Project $project = null): array
    {
        return array_values(Project::query()->when($project?->id, fn(Builder $query, int $projectId) => $query->whereKeyNot($projectId))
            ->whereHas('channels', fn(Builder $query) => $query->whereIn('type', [ChannelType::YOUTUBE, ChannelType::INSTAGRAM])->whereNotNull('credentials'))
            ->orderBy('name')->limit(100)->get(['id', 'name'])
            ->map(fn(Project $source): array => ['id' => $source->id, 'name' => $source->name])->all());
    }
}
