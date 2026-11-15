<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Data\Characters\CharacterReferenceData;
use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class UploadCharacterReference
{
    public function run(CharacterRevision $revision, CharacterReferenceData $data): Media
    {
        $path = $data->file->getRealPath();
        $size = $data->file->getSize();

        if (! $data->file->isValid() || $path === false || $size < 1 || $size > CharacterReferenceData::MAX_SIZE_KB * 1024) {
            throw ValidationException::withMessages(['file' => 'Upload a reference image no larger than 10 MB.']);
        }

        $dimensions = @getimagesize($path);
        $contents = file_get_contents($path);

        if ($dimensions === false || $contents === false
            || ! in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)
            || $dimensions[0] < 64 || $dimensions[1] < 64
            || $dimensions[0] > 6000 || $dimensions[1] > 6000
            || $dimensions[0] * $dimensions[1] > 20000000
            || $this->isAnimated($contents, $dimensions[2])) {
            throw ValidationException::withMessages(['file' => 'Use a static PNG, JPEG or WebP reference between 64 and 6000 pixels and at most 20 megapixels.']);
        }

        $revision = CharacterRevision::query()->lockForUpdate()->findOrFail($revision->id);

        if ($revision->status !== RevisionStatus::DRAFT) {
            throw ValidationException::withMessages(['revision' => 'Create a draft version before changing character references.']);
        }

        if ($revision->lock_version !== $data->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'The character references were changed. Refresh them before uploading.']);
        }

        if ($revision->character?->archived_at !== null || $revision->character?->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'An archived character or series cannot receive references.']);
        }

        if ($revision->getMedia(CharacterRevision::REFERENCES)->count() >= CharacterReferenceData::MAX_REFERENCES) {
            throw ValidationException::withMessages(['file' => 'A character version can contain at most 30 references.']);
        }

        $extension = match ($dimensions[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            default => 'webp',
        };
        $media = null;

        try {
            $media = $revision->addMedia($data->file)
                ->usingName($data->label !== '' ? $data->label : $data->view->label())
                ->usingFileName(Str::uuid() . '.' . $extension)
                ->withCustomProperties([
                    'view' => $data->view->value,
                    'label' => $data->label,
                    'checksum' => hash('sha256', $contents),
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                ])
                ->toMediaCollection(CharacterRevision::REFERENCES);
            $revision->lock_version++;
            $revision->save();
        } catch (Throwable $exception) {
            $media?->delete();

            throw $exception;
        }

        return $media;
    }

    private function isAnimated(string $contents, int $type): bool
    {
        $size = strlen($contents);
        $offset = $type === IMAGETYPE_PNG ? 8 : 12;

        if ($type !== IMAGETYPE_PNG && $type !== IMAGETYPE_WEBP) {
            return false;
        }

        while ($offset + 8 <= $size) {
            if ($type === IMAGETYPE_PNG) {
                $length = (ord($contents[$offset]) << 24) | (ord($contents[$offset + 1]) << 16)
                    | (ord($contents[$offset + 2]) << 8) | ord($contents[$offset + 3]);
                $chunk = substr($contents, $offset + 4, 4);

                if ($chunk === 'acTL') {
                    return true;
                }

                $offset += 12 + $length;
            } else {
                $length = ord($contents[$offset + 4]) | (ord($contents[$offset + 5]) << 8)
                    | (ord($contents[$offset + 6]) << 16) | (ord($contents[$offset + 7]) << 24);
                $chunk = substr($contents, $offset, 4);

                if ($chunk === 'ANIM' || $chunk === 'ANMF') {
                    return true;
                }

                $offset += 8 + $length + ($length % 2);
            }
        }

        return false;
    }
}
