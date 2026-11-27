<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Classes\Generators\VoiceSample;
use App\Data\Characters\CharacterReferenceData;
use App\Enums\ReferenceView;
use App\Enums\RevisionStatus;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Repositories\CharacterRepository;
use App\Repositories\CharacterRevisionRepository;
use App\Repositories\MediaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class UploadCharacterReference
{
    public function __construct(private readonly CharacterRevisionRepository $characterRevisions, private readonly MediaRepository $mediaRepository, private readonly Filesystem $files, private readonly CharacterRepository $characters) {}

    public function run(CharacterRevision $revision, CharacterReferenceData $data): Media
    {
        $path = $data->file->getRealPath();
        $size = $data->file->getSize();

        if (! $data->file->isValid() || $path === false || $size < 1 || $size > CharacterReferenceData::MAX_SIZE_KB * 1024) {
            throw ValidationException::withMessages(['file' => 'Upload a reference file no larger than 10 MB.']);
        }

        $voice = $data->view === ReferenceView::VOICE;

        $character = $voice ? $this->characters->lockedWithSeries($revision->character_id) : null;

        $revision = $this->characterRevisions->lockedForReferences($revision->id);

        if (! $voice && $revision->status !== RevisionStatus::DRAFT) {
            throw ValidationException::withMessages(['revision' => 'Create a draft version before changing character references.']);
        }

        $expectedVersion = $voice ? $data->lock_version : ($data->lock_version ?? 0);

        if ($revision->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'The character references were changed. Refresh them before uploading.']);
        }

        if ($revision->character?->archived_at !== null || $revision->character?->series?->archived_at !== null) {
            throw ValidationException::withMessages(['character' => 'An archived character or series cannot receive references.']);
        }

        if ($character instanceof Character && ! $this->characterRevisions->acceptsVoiceSample($revision, $character)) {
            throw ValidationException::withMessages(['revision' => 'Откройте текущую утверждённую версию или последний черновик персонажа.']);
        }

        $collection = $voice ? CharacterRevision::VOICE_SAMPLE : CharacterRevision::REFERENCES;

        $previous = $voice ? $revision->getFirstMedia($collection) : null;

        if ($previous?->id !== $data->replace_voice_id) {
            throw ValidationException::withMessages(['file' => 'Проверьте текущий образец голоса перед заменой.']);
        }

        if (! $voice && $revision->getMedia($collection)->count() >= CharacterReferenceData::MAX_REFERENCES) {
            throw ValidationException::withMessages(['file' => 'A character version can contain at most 30 references.']);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw ValidationException::withMessages(['file' => 'Не удалось прочитать файл.']);
        }

        if ($voice) {
            $sample = VoiceSample::fromUpload($contents, $data->file->getMimeType());
            $contents = $sample->wav;
            $properties = ['duration_ms' => $sample->durationMs];
            $extension = 'wav';
        } else {
            [$extension, $properties] = $this->imageProperties($path, $contents);
        }

        $label = $data->label ?? '';
        $media = null;
        $fileName = Str::uuid() . '.' . $extension;

        try {
            $file = $voice ? $revision->addMediaFromString($contents) : $revision->addMedia($data->file);
            $media = $file
                ->usingName($label !== '' ? $label : $data->view->label())
                ->usingFileName($fileName)
                ->withCustomProperties([
                    'view' => $data->view->value,
                    'original_name' => $data->file->getClientOriginalName(),
                    'label' => $label,
                    'checksum' => hash('sha256', $contents),
                    ...$properties,
                ])
                ->toMediaCollection($collection);
            $this->characterRevisions->incrementReferenceVersion($revision);

            if ($voice) {
                DB::afterRollBack(fn() => $this->discardFiles($media));
            }

            if ($previous instanceof Media && $revision->status === RevisionStatus::APPROVED) {
                $this->mediaRepository->retainPreviousVoiceSample($previous);
            } elseif ($previous instanceof Media) {
                $previous->deleteQuietly();
                DB::afterCommit(fn() => rescue(fn() => $this->discardFiles($previous)));
            }
        } catch (Throwable $exception) {
            $media ??= $this->mediaRepository->referenceByFileName($revision, $fileName);
            $media?->delete();

            throw $exception;
        }

        return $media;
    }

    public function discardFiles(Media $media): void
    {
        $this->files->removeAllFiles($media);
    }

    /** @return array{string, array{width: int, height: int}} */
    private function imageProperties(string $path, string $contents): array
    {
        $dimensions = @getimagesize($path);

        if ($dimensions === false
            || ! in_array($dimensions[2], [
                IMAGETYPE_PNG,
                IMAGETYPE_JPEG,
                IMAGETYPE_WEBP,
            ], true)
            || $dimensions[0] < 64 || $dimensions[1] < 64
            || $dimensions[0] > 6000 || $dimensions[1] > 6000
            || $dimensions[0] * $dimensions[1] > 20000000
            || $this->isAnimated($contents, $dimensions[2])) {
            throw ValidationException::withMessages(['file' => 'Use a static PNG, JPEG or WebP reference between 64 and 6000 pixels and at most 20 megapixels.']);
        }

        $extension = match ($dimensions[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            default => 'webp',
        };

        return [$extension, ['width' => $dimensions[0], 'height' => $dimensions[1]]];
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
