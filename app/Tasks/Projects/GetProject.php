<?php

declare(strict_types=1);

namespace App\Tasks\Projects;

use App\Models\Project;

class GetProject
{
    public function run(Project $project): Project
    {
        $project->loadCount('prompts', 'readyPrompts', 'channels');
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
