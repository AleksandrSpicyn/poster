<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Data\Characters\CharacterData;
use App\Models\Character;
use App\Models\Series;
use App\Tasks\Characters\SaveCharacter;
use Illuminate\Support\Facades\DB;

readonly class SaveCharacterAction
{
    public function __construct(private SaveCharacter $saveCharacter) {}

    public function run(Series $series, CharacterData $data, ?Character $character = null): Character
    {
        return DB::transaction(fn(): Character => $this->saveCharacter->run($series, $data, $character));
    }
}
