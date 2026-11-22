<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Episodes\EpisodeScriptData;
use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;

/** @extends EloquentRepository<CharacterRevision> */
readonly class CharacterRevisionRepository extends EloquentRepository
{
    protected function model(): string
    {
        return CharacterRevision::class;
    }

    public function lockedForCharacter(int $characterId, int $id): CharacterRevision
    {
        return CharacterRevision::query()->with('media')
            ->where('character_id', $characterId)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function nextNumber(Character $character): int
    {
        return ($character->revisions()->orderByDesc('number')->first()->number ?? 0) + 1;
    }

    /** @return Collection<int, CharacterRevision> */
    public function approvedSelection(Series $series, EpisodeScriptData $script): Collection
    {
        return CharacterRevision::query()
            ->whereIn('id', $script->character_revision_ids)
            ->where('status', RevisionStatus::APPROVED)
            ->whereHas('character', fn($query) => $query->where('series_id', $series->id)->whereNull('archived_at'))
            ->get();
    }

    /** @return Collection<int, CharacterRevision> */
    public function currentForContext(int $seriesId, ?int $leadId = null): Collection
    {
        return CharacterRevision::query()
            ->with('character')
            ->where('status', RevisionStatus::APPROVED)
            ->whereHas('character', fn($query) => $query->where('series_id', $seriesId)
                ->whereNull('archived_at')
                ->whereColumn('characters.current_revision_id', 'character_revisions.id'))
            ->when($leadId !== null, fn($query) => $query->orderByRaw('CASE WHEN character_id = ? THEN 0 ELSE 1 END', [$leadId]))
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    public function lockedForReferences(int $id): CharacterRevision
    {
        return CharacterRevision::query()->with('character.series', 'media')->lockForUpdate()->findOrFail($id);
    }
}
