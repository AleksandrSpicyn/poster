<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Prompt;
use App\Models\Shot;

/** @extends EloquentRepository<Shot> */
readonly class ShotRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Shot::class;
    }

    public function forGeneration(int $id): Shot
    {
        return Shot::query()->with(['scene.shots', 'scene.episodeRevision.episode.series.project', 'scene.episodeRevision.characterRevisions.character', 'scene.episodeRevision.characterRevisions.media'])->findOrFail($id);
    }

    public function forPrompt(Prompt $prompt): ?Shot
    {
        return $prompt->shot()->first();
    }
}
