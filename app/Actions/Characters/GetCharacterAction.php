<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Data\Characters\CharacterPageData;
use App\Data\Characters\CharacterWorkspaceData;
use App\Models\Character;
use App\Tasks\Characters\GetCharacter;

readonly class GetCharacterAction
{
    public function __construct(private GetCharacter $getCharacter) {}

    public function run(Character $character, CharacterPageData $data): CharacterWorkspaceData
    {
        return $this->getCharacter->run($character, $data);
    }
}
