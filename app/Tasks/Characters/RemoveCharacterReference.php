<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RemoveCharacterReference
{
    public function run(CharacterRevision $revision, Media $media, int $expectedVersion): void
    {
        $revision = CharacterRevision::query()->lockForUpdate()->findOrFail($revision->id);

        if ($revision->status !== RevisionStatus::DRAFT) {
            throw ValidationException::withMessages(['revision' => 'References of an approved character version cannot be removed.']);
        }

        if ($revision->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'The character references were changed. Refresh them before removing a file.']);
        }

        if ($revision->character?->archived_at !== null || $revision->character?->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'References of an archived character or series cannot be changed.']);
        }

        $media = $revision->media()
            ->where('collection_name', CharacterRevision::REFERENCES)
            ->findOrFail($media->id);
        $revision->lock_version++;
        $revision->save();
        $media->delete();
    }
}
