<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Data\Characters\CharacterRevisionData;
use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use Illuminate\Validation\ValidationException;

class SaveCharacterRevision
{
    public function run(Character $character, CharacterRevisionData $data, ?CharacterRevision $revision = null): CharacterRevision
    {
        $character = Character::query()->lockForUpdate()->findOrFail($character->id);

        if ($character->archived_at !== null || $character->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'An archived character or series cannot receive versions.']);
        }

        if ($revision instanceof CharacterRevision) {
            $revision = CharacterRevision::query()
                ->where('character_id', $character->id)
                ->lockForUpdate()
                ->findOrFail($revision->id);

            if ($revision->status !== RevisionStatus::DRAFT) {
                throw ValidationException::withMessages(['revision' => 'Create a new version instead of changing an approved character.']);
            }

            if ($data->lock_version !== $revision->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'The character version was changed. Refresh it before saving.']);
            }
        }

        $revision ??= new CharacterRevision([
            'character_id' => $character->id,
            'number' => ($character->revisions()->orderByDesc('number')->first()->number ?? 0) + 1,
            'status' => RevisionStatus::DRAFT,
        ]);
        $revision->fill(['description' => $data->description, 'appearance' => $data->appearance, 'voice' => $data->voice]);

        if ($revision->exists && $revision->isDirty(['description', 'appearance', 'voice'])) {
            $revision->lock_version++;
        }

        $revision->save();

        return $revision->refresh();
    }
}
