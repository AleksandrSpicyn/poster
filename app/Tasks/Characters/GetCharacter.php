<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Data\Characters\CharacterWorkspaceData;
use App\Models\Character;
use App\Repositories\CharacterRepository;

class GetCharacter
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function run(Character $character, int $page): CharacterWorkspaceData
    {
        return $this->characters->workspace($character, $page);
    }
}
