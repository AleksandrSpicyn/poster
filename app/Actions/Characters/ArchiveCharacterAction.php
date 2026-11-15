<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Models\Character;
use App\Tasks\Characters\ArchiveCharacter;
use Illuminate\Support\Facades\DB;

readonly class ArchiveCharacterAction
{
    public function __construct(private ArchiveCharacter $archive) {}

    public function run(Character $character, int $expectedVersion): void
    {
        DB::transaction(fn() => $this->archive->run($character, $expectedVersion));
    }
}
