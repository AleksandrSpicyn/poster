<?php

declare(strict_types=1);

namespace App\Actions\Characters;

use App\Models\CharacterRevision;
use App\Tasks\Characters\GetCharacterReference;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetCharacterReferenceAction
{
    public function __construct(private GetCharacterReference $getReference) {}

    public function run(CharacterRevision $revision, Media $media): string
    {
        return $this->getReference->run($revision, $media);
    }
}
