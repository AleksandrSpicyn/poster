<?php

declare(strict_types=1);

namespace App\Tasks\Production;

use App\Models\ProductionRun;

class GetProductionRun
{
    public function run(int $id): ProductionRun
    {
        return ProductionRun::query()->with([
            'actor', 'project.channels',
            'series.automationUser', 'series.characters.currentRevision.media',
            'series.characters.revisions' => fn($query) => $query->with('media')->orderByDesc('number'),
            'series.episodes' => fn($query) => $query->orderByDesc('number')->limit(1),
            'episode.currentRevision.scenes.shots.prompts.operations',
            'episode.currentRevision.scenes.shots.prompts.media',
            'assembly.media', 'assembly.publications', 'prompt.posts',
        ])->findOrFail($id);
    }
}
