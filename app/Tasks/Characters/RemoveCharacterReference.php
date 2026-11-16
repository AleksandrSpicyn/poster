<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use App\Repositories\CharacterRevisionRepository;
use App\Repositories\MediaRepository;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RemoveCharacterReference
{
    public function __construct(
        private readonly CharacterRevisionRepository $characterRevisions,
        private readonly MediaRepository $media,
    ) {}

    public function run(CharacterRevision $revision, Media $media, int $expectedVersion): void
    {
        $revision = $this->characterRevisions->lockedForReferences($revision->id);

        if ($revision->status !== RevisionStatus::DRAFT) {
            throw ValidationException::withMessages(['revision' => 'References of an approved character version cannot be removed.']);
        }

        if ($revision->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'The character references were changed. Refresh them before removing a file.']);
        }

        if ($revision->character?->archived_at !== null || $revision->character?->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'References of an archived character or series cannot be changed.']);
        }

        $media = $this->media->referenceForRevision($revision, $media->id);
        $revision->lock_version++;
        $this->characterRevisions->save($revision);
        $media->delete();
    }
}
