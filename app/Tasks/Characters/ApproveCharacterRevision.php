<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ApproveCharacterRevision
{
    public function run(CharacterRevision $revision, User $user, int $expectedVersion): CharacterRevision
    {
        $character = Character::query()->lockForUpdate()->findOrFail($revision->character_id);
        $revision = CharacterRevision::query()
            ->where('character_id', $character->id)
            ->lockForUpdate()
            ->findOrFail($revision->id);

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

        $revision->fill([
            'status' => RevisionStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $user->id,
            'lock_version' => $revision->lock_version + 1,
        ])->save();
        $character->fill(['current_revision_id' => $revision->id, 'lock_version' => $character->lock_version + 1])->save();

        return $revision->refresh();
    }
}
