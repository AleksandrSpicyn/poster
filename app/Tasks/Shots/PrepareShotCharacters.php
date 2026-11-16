<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Data\Shots\PinnedCharacterData;
use App\Data\Shots\PinnedReferenceData;
use App\Enums\RevisionStatus;
use App\Models\CharacterRevision;
use App\Models\Shot;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class PrepareShotCharacters
{
    /** @return list<PinnedCharacterData> */
    public function run(Shot $shot): array
    {
        $scene = $shot->scene ?? throw new RuntimeException('Shot scene is missing.');
        $revision = $scene->episodeRevision ?? throw new RuntimeException('Shot screenplay is missing.');
        $episode = $revision->episode ?? throw new RuntimeException('Shot episode is missing.');
        $series = $episode->series ?? throw new RuntimeException('Shot series is missing.');
        $characters = $revision->characterRevisions->map(function (CharacterRevision $character) use ($series): PinnedCharacterData {
            if ($character->status !== RevisionStatus::APPROVED || $character->character?->series_id !== $series->id) {
                throw ValidationException::withMessages(['characters' => 'Every character must have an approved version from this series.']);
            }

            $references = $character->getMedia(CharacterRevision::REFERENCES)->map(function (Media $media): PinnedReferenceData {
                $checksum = hash_file('sha256', $media->getPath());

                if ($checksum === false) {
                    throw ValidationException::withMessages(['references' => 'An approved reference file is missing.']);
                }

                return new PinnedReferenceData($media->id, $checksum);
            })->values()->all();

            if ($references === []) {
                throw ValidationException::withMessages(['characters' => 'Upload approved appearance references before generation.']);
            }

            return new PinnedCharacterData($character->id, $character->character_id, $character->character->name, $character->description, $character->appearance, $character->voice, array_values($references));
        })->values()->all();

        if ($characters === [] || count($characters) > 10) {
            throw ValidationException::withMessages(['characters' => 'A shot requires between one and ten pinned characters.']);
        }

        return array_values($characters);
    }
}
