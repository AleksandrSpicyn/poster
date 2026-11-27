<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Data\Characters\CharacterRevisionData;
use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Repositories\CharacterRepository;
use App\Repositories\CharacterRevisionRepository;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class SaveCharacterRevision
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly CharacterRevisionRepository $characterRevisions,
    ) {}

    public function run(Character $character, CharacterRevisionData $data, ?CharacterRevision $revision = null): CharacterRevision
    {
        $character = $this->characters->lockedWithSeries($character->id);

        if ($character->archived_at !== null || $character->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'An archived character or series cannot receive versions.']);
        }

        if ($revision instanceof CharacterRevision) {
            $revision = $this->characterRevisions->lockedForCharacter($character->id, $revision->id);

            if ($revision->status !== RevisionStatus::DRAFT) {
                throw ValidationException::withMessages(['revision' => 'Create a new version instead of changing an approved character.']);
            }

            if ($data->lock_version !== $revision->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'The character version was changed. Refresh it before saving.']);
            }
        }

        $revision ??= new CharacterRevision([
            'character_id' => $character->id,
            'number' => $this->characterRevisions->nextNumber($character),
            'status' => RevisionStatus::DRAFT,
        ]);
        $revision->fill([
            'description' => $data->description,
            'appearance' => $data->appearance,
            'voice' => $data->voice,
        ]);

        if (! $data->title instanceof Optional) {
            $revision->title = $data->title;
        }

        if ($revision->exists && $revision->isDirty([
            'title',
            'description',
            'appearance',
            'voice',
        ])) {
            $revision->lock_version++;
        }

        $newVersion = ! $revision->exists;
        $this->characterRevisions->save($revision);

        if ($newVersion && $character->current_revision_id !== null) {
            $current = $this->characterRevisions->lockedForCharacter($character->id, $character->current_revision_id);
            $current->getFirstMedia(CharacterRevision::VOICE_SAMPLE)?->copy($revision, CharacterRevision::VOICE_SAMPLE, 'local');
        }

        return $this->characterRevisions->refresh($revision);
    }
}
