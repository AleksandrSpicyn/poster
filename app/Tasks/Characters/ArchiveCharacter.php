<?php

declare(strict_types=1);

namespace App\Tasks\Characters;

use App\Models\Character;
use Illuminate\Validation\ValidationException;

class ArchiveCharacter
{
    public function run(Character $character, int $expectedVersion): void
    {
        $character = Character::query()->lockForUpdate()->findOrFail($character->id);

        if ($character->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['lock_version' => 'This record was changed. Refresh it before archiving.']);
        }

        if ($character->archived_at !== null) {
            return;
        }

        $character->update(['archived_at' => now(), 'lock_version' => $character->lock_version + 1]);
    }
}
