<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Data\Characters\CharacterRevisionData;
use App\Models\Character;
use App\Models\CharacterRevision;
use App\Tasks\Characters\SaveCharacterRevision;
use Illuminate\Support\Facades\DB;

readonly class SaveCharacterRevisionAction
{
    public function __construct(private SaveCharacterRevision $saveCharacterRevision) {}

    public function run(Character $character, CharacterRevisionData $data, ?CharacterRevision $revision = null): CharacterRevision
    {
        return DB::transaction(fn(): CharacterRevision => $this->saveCharacterRevision->run($character, $data, $revision));
    }
}
