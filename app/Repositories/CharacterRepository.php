<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Characters\CharacterPageData;
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

    public function workspace(Character $character, CharacterPageData $data): CharacterWorkspaceData
    {
        $character->loadMissing('series.project', 'currentRevision.media');

        $episodes = Episode::query()->where('series_id', $character->series_id)
            ->whereHas('revisions.characterRevisions', fn($query) => $query->where('character_id', $character->id));

        return new CharacterWorkspaceData(
            character: $character,
            episodesCount: (clone $episodes)->count(),
            revisions: $character->revisions()
                ->with('media')
                ->orderByDesc('number')
                ->paginate(5, page: $data->page)
                ->withQueryString(),
            selectedEpisode: $data->episode === null ? null : $episodes->findOrFail($data->episode, ['id', 'number']),
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
