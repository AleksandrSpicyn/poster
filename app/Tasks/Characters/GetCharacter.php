<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Models\Character;
use App\Models\CharacterRevision;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCharacter
{
    /** @return array{character: Character, revisions: LengthAwarePaginator<int, CharacterRevision>} */
    public function run(Character $character, int $page): array
    {
        $character->load('series.project', 'currentRevision');

        return [
            'character' => $character,
            'revisions' => $character->revisions()
                ->with('media')
                ->orderByDesc('number')
                ->paginate(5, page: $page)
                ->withQueryString(),
        ];
    }
}
