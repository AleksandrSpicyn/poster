<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Models\CharacterRevision;
use App\Tasks\Characters\RemoveCharacterReference;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class RemoveCharacterReferenceAction
{
    public function __construct(private RemoveCharacterReference $removeCharacterReference) {}

    public function run(CharacterRevision $revision, Media $media, int $expectedVersion): void
    {
        DB::transaction(function () use ($revision, $media, $expectedVersion): void {
            $this->removeCharacterReference->run($revision, $media, $expectedVersion);
        });
    }
}
