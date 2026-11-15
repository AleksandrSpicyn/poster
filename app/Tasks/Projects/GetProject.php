<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

class GetProject
{
    public function run(Project $project): Project
    {
        $project->loadCount([
            'prompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'readyPrompts' => static fn(Builder $query) => $query->whereNull('shot_id'),
            'channels', 'series',
        ]);
        $project->setRelation('channels', $project
            ->channels()
            ->select('id', 'project_id', 'name', 'type')
            ->withCount('posts')
            ->orderBy('name')
            ->orderBy('id')
            ->get());

        return $project;
    }
}
