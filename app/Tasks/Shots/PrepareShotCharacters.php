<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Data\Shots\PinnedCharacterData;
use App\Data\Shots\PinnedReferenceData;
use App\Data\Shots\PinnedVoiceSampleData;
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
        $speakers = array_column($shot->dialogue, 'character_id');
        $visible = $shot->direction()?->visible_character_ids;
        $cast = $visible === null ? $revision->characterRevisions : $revision->characterRevisions->whereIn('character_id', [...$visible, ...$speakers]);
        $characters = $cast->map(function (CharacterRevision $character) use ($series, $speakers): PinnedCharacterData {
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

            $voiceSample = null;

            if (in_array($character->character_id, $speakers, true)) {
                $media = $character->getFirstMedia(CharacterRevision::VOICE_SAMPLE);

                if ($media instanceof Media) {
                    $checksum = $media->getCustomProperty('checksum');
                    $duration = $media->getCustomProperty('duration_ms');

                    if (! is_int($duration) || ! is_string($checksum) || ! is_file($media->getPath()) || hash_file('sha256', $media->getPath()) !== $checksum) {
                        throw ValidationException::withMessages(['voice' => 'Утверждённый образец голоса недоступен или изменён.']);
                    }

                    $voiceSample = new PinnedVoiceSampleData($media->id, $checksum, $duration);
                }
            }

            return new PinnedCharacterData($character->id, $character->character_id, $character->character->name, $character->description, $character->appearance, $character->voice, array_values($references), $voiceSample);
        })->values()->all();

        if (($characters === [] && $visible !== []) || count($characters) > 10) {
            throw ValidationException::withMessages(['characters' => 'A shot requires between one and ten pinned characters.']);
        }

        return array_values($characters);
    }
}
