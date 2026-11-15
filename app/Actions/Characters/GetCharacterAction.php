<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\CharacterRevision;
use App\Tasks\Characters\GetCharacter;
use Illuminate\Pagination\LengthAwarePaginator;

readonly class GetCharacterAction
{
    public function __construct(private GetCharacter $getCharacter) {}

    /** @return array{character: Character, revisions: LengthAwarePaginator<int, CharacterRevision>} */
    public function run(Character $character, int $page = 1): array
    {
        return $this->getCharacter->run($character, $page);
    }
}
