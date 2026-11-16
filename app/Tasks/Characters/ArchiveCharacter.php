<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Models\Character;
use App\Repositories\CharacterRepository;
use Illuminate\Validation\ValidationException;

class ArchiveCharacter
{
    public function __construct(private readonly CharacterRepository $characters) {}

    public function run(Character $character, int $expectedVersion): void
    {
        $character = $this->characters->findLockedOrFail($character->id);

        if ($character->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'This record was changed. Refresh it before archiving.']);
        }

        if ($character->archived_at !== null) {
            return;
        }

        $this->characters->update($character, ['archived_at' => now(), 'lock_version' => $character->lock_version + 1]);
    }
}
