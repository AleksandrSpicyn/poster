<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use App\Models\User;
use App\Repositories\CharacterRepository;
use App\Repositories\CharacterRevisionRepository;
use Illuminate\Validation\ValidationException;

class ApproveCharacterRevision
{
    public function __construct(
        private readonly CharacterRepository $characters,
        private readonly CharacterRevisionRepository $characterRevisions,
    ) {}

    public function run(CharacterRevision $revision, User $user, int $expectedVersion): CharacterRevision
    {
        $character = $this->characters->lockedWithSeries($revision->character_id);
        $revision = $this->characterRevisions->lockedForCharacter($character->id, $revision->id);

        if ($revision->status === RevisionStatus::APPROVED) {
            return $revision;
        }

        if ($character->archived_at !== null || $character->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'An archived character or series cannot be approved.']);
        }

        if ($revision->status !== RevisionStatus::DRAFT) {
            throw ValidationException::withMessages(['revision' => 'Only a draft character version can be approved.']);
        }

        if ($revision->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'The character version was changed. Review it before approval.']);
        }

        if ($revision->getMedia(CharacterRevision::REFERENCES)->isEmpty()) {
            throw ValidationException::withMessages(['references' => 'Upload at least one reference before approving the character.']);
        }

        $this->characterRevisions->save($revision->fill([
            'status' => RevisionStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'lock_version' => $revision->lock_version + 1,
        ]));
        $this->characters->save($character->fill(['current_revision_id' => $revision->id, 'lock_version' => $character->lock_version + 1]));

        return $this->characterRevisions->refresh($revision);
    }
}
