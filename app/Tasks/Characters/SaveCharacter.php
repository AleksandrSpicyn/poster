<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Data\Characters\CharacterData;
use App\Models\Character;
use App\Models\Series;
use App\Repositories\CharacterRepository;
use App\Repositories\SeriesRepository;
use Illuminate\Validation\ValidationException;

class SaveCharacter
{
    public function __construct(
        private readonly SeriesRepository $series,
        private readonly CharacterRepository $characters,
    ) {}

    public function run(Series $series, CharacterData $data, ?Character $character = null): Character
    {
        $series = $this->series->findLockedOrFail($series->id);

        if ($series->archived_at !== null) {
            throw ValidationException::withMessages(['series' => 'An archived series cannot receive characters.']);
        }

        if ($character instanceof Character) {
            $character = $this->characters->lockedForSeries($series->id, $character->id);

            if ($data->lock_version !== $character->lock_version) {
                throw ValidationException::withMessages(['lock_version' => 'The character was changed. Refresh it before saving.']);
            }

            if ($character->archived_at !== null) {
                throw ValidationException::withMessages(['character' => 'An archived character cannot be changed.']);
            }
        }

        $character ??= new Character(['series_id' => $series->id]);
        $character->fill(['name' => $data->name]);

        if ($character->exists && $character->isDirty('name')) {
            $character->lock_version++;
        }

        $this->characters->save($character);

        return $this->characters->refresh($character);
    }
}
