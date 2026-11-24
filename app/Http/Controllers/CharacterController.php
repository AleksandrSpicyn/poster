<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Project;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

abstract class CharacterController
{
    protected function redirectToCharacter(Project $project, Series $series, Character $character): RedirectResponse
    {
        return to_route('characters.show', [
            'project' => $project,
            'series' => $series,
            'character' => $character,
        ]);
    }
}
