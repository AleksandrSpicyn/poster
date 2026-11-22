<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Characters\CharacterWorkspaceData;
use App\Models\Character;
use App\Models\Episode;

/** @extends EloquentRepository<Character> */
readonly class CharacterRepository extends EloquentRepository
{
    protected function model(): string
    {
        return Character::class;
    }

    public function workspace(Character $character, int $page): CharacterWorkspaceData
    {
        $character->load('series.project', 'currentRevision');

        return new CharacterWorkspaceData(
            character: $character,
            episodesCount: Episode::query()
                ->where('series_id', $character->series_id)
                ->whereHas('revisions.characterRevisions', fn($query) => $query->where('character_id', $character->id))
                ->count(),
            revisions: $character->revisions()
                ->with('media')
                ->orderByDesc('number')
                ->paginate(5, page: $page)
                ->withQueryString(),
        );
    }

    public function lockedForSeries(int $seriesId, int $id): Character
    {
        return Character::query()
            ->where('series_id', $seriesId)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function lockedWithSeries(int $id): Character
    {
        return Character::query()->with('series')->lockForUpdate()->findOrFail($id);
    }
}
